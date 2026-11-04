<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Auth\TooManyAttemptsException;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Plugin\Plugins;
use Frootbox\Core\Support\Message;
use Frootbox\Core\Url\Target;
use Frootbox\Database\Ulid;
use Frootbox\Forms\Captcha\CaptchaRequest;
use Frootbox\Forms\Captcha\Captchas;
use Frootbox\Forms\Captcha\Verdict;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Entry\EntryMails;
use Frootbox\Forms\Form\FormData;
use Frootbox\Forms\Form\FormStore;
use Frootbox\Forms\Spam\SpamRules;
use Frootbox\Forms\Spam\SpamSubject;

/**
 * A visitor sends a form (decision 0222). Anyone may, so it is guarded
 * against spam in layers: a hidden field people leave empty and the signed
 * time the form was shown (spam is dropped), at most 5 sendings per
 * address in 10 minutes, the captcha of the form and the rules for the
 * content (decision 0261). What the captcha or a rule finds suspicious is
 * kept in the spam folder without a mail. Spam gets the answer of a
 * success. Not undoable: the mails leave the house.
 *
 * @implements Action<SubmitResult>
 */
#[AsAction('frootbox/forms/form.submit', 'Sends a published form as a visitor: keeps the entry and mails it to the recipients', public: true, undoable: false)]
final readonly class SubmitForm implements Action
{
    /**
     * @param array<mixed>      $values
     * @param array<mixed>|null $captcha
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Description('The values by key of the field: texts, a list of texts for checkboxes, true for a checked box')]
        public array $values,
        #[Description('The language the visitor saw the form in; left out for the first of the website')]
        public ?string $language = null,
        #[Description('The token the form was shown with, from its field "_token"')]
        #[Length(max: 200)]
        public ?string $token = null,
        #[Description('The hidden field people leave empty')]
        #[Length(max: 1000)]
        public ?string $website = null,
        #[Description('The page the form was sent from')]
        #[Pattern(Ulid::PATTERN)]
        public ?string $page = null,
        #[Description('What the form is about when it was sent from the address of an object, e.g. "frootbox/jobs/job:01J…" for an application')]
        #[Length(max: 150)]
        #[Pattern(Target::PATTERN)]
        public ?string $about = null,
        #[Description('What the captcha of the form gave, its fields "captcha[…]" by name (decision 0261)')]
        public ?array $captcha = null,
    ) {}

    public function handle(FormStore $store, Languages $languages, FormToken $tokens, Sendings $sendings, Entries $entries, EntryMails $mails, CurrentContext $context, FormFiles $files, Plugins $subpages, Captchas $captchas, SpamRules $rules): SubmitResult
    {
        $action = 'frootbox/forms/form.submit';
        $form = $store->published($this->id) ?? throw new InvalidInputException($action, ['/id' => new Message('The form is not online.')]);
        $language = $form->language(null !== $this->language && $languages->has($this->language) ? $this->language : $languages->default()->code) ?? $languages->default()->code;
        $result = new SubmitResult($form->text($language)->success);

        if ('' !== trim($this->website ?? '') || !$tokens->passes($this->id, $this->token)) {
            return $result;
        }

        $submission = new Submission($form, $language, $this->values, $files);

        if ([] !== $submission->errors) {
            throw new InvalidInputException($action, $submission->errors);
        }

        $address = $context->get()->address;
        $wait = $sendings->wait($this->id, $address);

        if ($wait > 0) {
            $minutes = max(1, (int) ceil($wait / 60));

            throw new TooManyAttemptsException($wait, 1 === $minutes
                ? new Message('You have sent this form several times. Try again in a minute.')
                : new Message('You have sent this form several times. Try again in {minutes} minutes.', ['minutes' => $minutes]));
        }

        $spam = $this->captcha($captchas, $form, $language, $address);
        $sendings->count($this->id, $address);
        $id = Ulid::generate();
        $fields = $submission->fields;
        $spam ??= $rules->check(new SpamSubject($this->id, $language, $fields));

        foreach ($fields as $index => $field) {
            if (isset($submission->uploads[$field['key']])) {
                $kept = $files->keep($id, $submission->uploads[$field['key']]);
                $fields[$index] = [...$field, 'value' => array_column($kept, 'name'), 'files' => $kept];
            }
        }

        [$about, $aboutTitle] = $this->about($subpages, $language);
        $entry = $entries->insert(['id' => $id, 'formId' => $this->id, 'language' => $language, 'fields' => $fields, 'pageId' => $this->page, 'about' => $about, 'aboutTitle' => $aboutTitle, 'spam' => $spam]);

        if (null === $spam) {
            $mails->send($form, $entry);
        }

        return $result;
    }

    /**
     * Checks the captcha of the form: an error for the visitor if wrong,
     * the reason if suspicious, null if it passed or there is none.
     */
    private function captcha(Captchas $captchas, FormData $form, string $language, ?string $address): ?string
    {
        $captcha = $captchas->of($form);

        if (null === $captcha) {
            return null;
        }

        $answer = [];

        foreach ($this->captcha ?? [] as $name => $value) {
            if (\is_string($value)) {
                $answer[(string) $name] = mb_substr($value, 0, 10_000);
            }
        }

        $checked = $captchas->provider($captcha)->verify(new CaptchaRequest($this->id, $language, $address), $answer);

        return match ($checked->verdict) {
            Verdict::Passed => null,
            Verdict::Wrong => throw new InvalidInputException('frootbox/forms/form.submit', ['/captcha' => $checked->message ?? new Message('The captcha is not solved.')]),
            Verdict::Suspicious => mb_substr($checked->message->text ?? 'The captcha is not solved.', 0, 300),
        };
    }

    /**
     * The object the form was sent from with its title, only if it is
     * published: a visitor cannot name anything else (decision 0230).
     *
     * @return array{string|null, string|null}
     */
    private function about(Plugins $subpages, string $language): array
    {
        $target = null === $this->about ? null : Target::tryParse($this->about);
        $title = null === $target || $target->isPage() ? null : $subpages->get($target->type)?->title($target->id, $language);

        return null === $target || null === $title ? [null, null] : [(string) $target, $title];
    }
}

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
use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Mail\Message as Mail;
use Frootbox\Core\Mail\SendMail;
use Frootbox\Core\Queue\Queue;
use Frootbox\Core\Support\Message;
use Frootbox\Core\Url\SubpageSources;
use Frootbox\Core\Url\Target;
use Frootbox\Database\Ulid;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Entry\Entry;
use Frootbox\Forms\Form\FormData;
use Frootbox\Forms\Form\FormStore;

/**
 * A visitor sends a form (decision 0222). Anyone may, so it is guarded
 * against spam without a captcha: a hidden field people leave empty, the
 * signed time the form was shown and at most 5 sendings per address in
 * 10 minutes. Spam gets the answer of a success but is neither kept nor
 * sent. Not undoable: the mails leave the house.
 *
 * @implements Action<SubmitResult>
 */
#[AsAction('frootbox/forms/form.submit', 'Sends a published form as a visitor: keeps the entry and mails it to the recipients', public: true, undoable: false)]
final readonly class SubmitForm implements Action
{
    /**
     * @param array<mixed> $values
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
    ) {}

    public function handle(FormStore $store, Languages $languages, FormToken $tokens, Sendings $sendings, Entries $entries, Queue $queue, Translator $translator, CurrentContext $context, FormFiles $files, SubpageSources $subpages): SubmitResult
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

        $sendings->count($this->id, $address);
        $id = Ulid::generate();
        $fields = $submission->fields;

        foreach ($fields as $index => $field) {
            if (isset($submission->uploads[$field['key']])) {
                $kept = $files->keep($id, $submission->uploads[$field['key']]);
                $fields[$index] = [...$field, 'value' => array_column($kept, 'name'), 'files' => $kept];
            }
        }

        [$about, $aboutTitle] = $this->about($subpages, $language);
        $entry = $entries->insert(['id' => $id, 'formId' => $this->id, 'language' => $language, 'fields' => $fields, 'pageId' => $this->page, 'about' => $about, 'aboutTitle' => $aboutTitle]);

        foreach ($form->recipients as $recipient) {
            $queue->push(SendMail::of($this->mail($form, $entry, $recipient, $submission->replyTo(), $translator, $languages->default()->code)));
        }

        return $result;
    }

    /**
     * The object the form was sent from with its title, only if it is
     * published: a visitor cannot name anything else (decision 0230).
     *
     * @return array{string|null, string|null}
     */
    private function about(SubpageSources $subpages, string $language): array
    {
        $target = null === $this->about ? null : Target::tryParse($this->about);
        $title = null === $target || $target->isPage() ? null : $subpages->get($target->type)?->title($target->id, $language);

        return null === $target || null === $title ? [null, null] : [(string) $target, $title];
    }

    /**
     * The entry as a plain text mail in the language of the backend.
     */
    private function mail(FormData $form, Entry $entry, string $recipient, ?string $replyTo, Translator $translator, string $default): Mail
    {
        $language = $translator->standard();
        $packages = ['frootbox/forms'];
        $lines = [];

        $files = false;

        foreach ($entry->fields as $field) {
            $value = Entry::text($field['value'], $translator->in($language, 'yes', $packages), $translator->in($language, 'no', $packages));
            $lines[] = $field['label'] . ':' . (str_contains($value, "\n") ? "\n" . $value . "\n" : ' ' . $value);
            $files = $files || [] !== ($field['files'] ?? []);
        }

        // Files stay on the server: a mail is no place for a CV (decision 0230).
        if ($files) {
            $lines[] = '';
            $lines[] = $translator->in($language, 'The files are with the entry in the backend.', $packages);
        }

        $title = $form->text($default)->title;

        return new Mail(
            [$recipient],
            null === $entry->aboutTitle
                ? $translator->in($language, 'New entry: {form}', $packages, ['form' => $title])
                : $translator->in($language, 'New entry: {form} – {about}', $packages, ['form' => $title, 'about' => $entry->aboutTitle]),
            implode("\n", $lines) . "\n",
            $replyTo,
        );
    }
}

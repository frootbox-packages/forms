<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Support\Message;
use Frootbox\Database\Ulid;
use Frootbox\Forms\Captcha\Captchas;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.update', 'Changes the texts, recipients or captcha in the draft of a form; what is left out stays as it is')]
final readonly class UpdateForm implements Action
{
    /**
     * @param array<mixed>|null $translations
     * @param list<string>|null $recipients
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Description('Changes by language, e.g. {"de": {"title": "Kontakt"}, "en": null}: texts left out stay, null removes a language')]
        public ?array $translations = null,
        #[ListOf('string')]
        #[Description('Email addresses that get every entry, replacing the ones before')]
        public ?array $recipients = null,
        #[Description('The captcha by name, "none" for none or "default" for the standard of the website (decision 0261)')]
        #[Length(max: 100)]
        public ?string $captcha = null,
    ) {}

    public function handle(FormEditor $editor, FormStore $store, Captchas $captchas): FormDetails
    {
        $current = $store->current($this->id);

        return $editor->saveDraft($this->id, $current->with(
            null === $this->translations ? null : $editor->translations('frootbox/forms/form.update', $this->translations, $current->translations),
            null === $this->recipients ? null : $editor->recipients('frootbox/forms/form.update', $this->recipients),
            captcha: $this->captcha($captchas),
        ));
    }

    /**
     * What the draft keeps: false for no change, null for the standard.
     */
    private function captcha(Captchas $captchas): string|false|null
    {
        return match (true) {
            null === $this->captcha => false,
            Captchas::DEFAULT === $this->captcha => null,
            Captchas::NONE === $this->captcha => $this->captcha,
            default => $this->known($captchas, $this->captcha),
        };
    }

    /**
     * A captcha that exists and is set up (decision 0262).
     */
    private function known(Captchas $captchas, string $name): string
    {
        $captcha = $captchas->get($name) ?? throw new InvalidInputException('frootbox/forms/form.update', ['/captcha' => new Message('There is no such captcha.')]);

        return $captchas->ready($captcha) ? $name : throw new InvalidInputException('frootbox/forms/form.update', ['/captcha' => new Message('Set up this captcha in the settings first.')]);
    }
}

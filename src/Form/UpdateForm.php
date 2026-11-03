<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.update', 'Changes the texts or recipients in the draft of a form; what is left out stays as it is')]
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
    ) {}

    public function handle(FormEditor $editor, FormStore $store): FormDetails
    {
        $current = $store->current($this->id);

        return $editor->saveDraft($this->id, $current->with(
            null === $this->translations ? null : $editor->translations('frootbox/forms/form.update', $this->translations, $current->translations),
            null === $this->recipients ? null : $editor->recipients('frootbox/forms/form.update', $this->recipients),
        ));
    }
}

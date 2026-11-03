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
use Frootbox\Database\Ulid;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.create', 'Creates a form as a draft; add fields with frootbox/forms/form.field-add, then frootbox/forms/form.publish puts it online')]
final readonly class CreateForm implements Action
{
    /**
     * @param array<mixed> $translations
     * @param list<string> $recipients
     */
    public function __construct(
        #[Description('Texts by language: {"de": {"title": "Kontakt", "button": "Absenden", "success": "Danke, wir melden uns."}}. Button and success may be left out for general texts.')]
        public array $translations,
        #[ListOf('string')]
        #[Description('Email addresses that get every entry, at most 10; none to only keep the entries')]
        public array $recipients = [],
    ) {}

    public function handle(FormEditor $editor): FormDetails
    {
        return $editor->saveDraft(Ulid::generate(), new FormData(
            $editor->translations('frootbox/forms/form.create', $this->translations),
            $editor->recipients('frootbox/forms/form.create', $this->recipients),
        ));
    }
}

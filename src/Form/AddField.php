<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Database\Ulid;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.field-add', 'Adds a field to the draft of a form', permission: 'frootbox/forms/form.update')]
final readonly class AddField implements Action
{
    /**
     * @param array<mixed> $translations
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        public FieldType $type,
        #[Description('Label, hint and for select, radio and checkboxes the options by language: {"de": {"label": "Ihr Anliegen", "options": ["Frage", "Lob"]}}')]
        public array $translations,
        #[Description('Whether the field must be filled in; consent is always required')]
        public bool $required = false,
        #[Description('The name of the value in entries, e.g. "email"; left out it comes from the label')]
        #[Pattern(Field::KEY_PATTERN)]
        public ?string $key = null,
        #[Description('Where it goes, from 0; left out at the end')]
        #[Range(min: 0)]
        public ?int $position = null,
    ) {}

    public function handle(FormEditor $editor, FormStore $store): FormDetails
    {
        $action = 'frootbox/forms/form.field-add';
        $current = $store->current($this->id);

        return $editor->saveDraft($this->id, $editor->addField($action, $current, $this->key, $this->type, $this->required, $editor->fieldTexts($action, $this->translations), $this->position));
    }
}

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
use Frootbox\Database\Ulid;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.field-update', 'Changes a field in the draft of a form; what is left out stays as it is', permission: 'frootbox/forms/form.update')]
final readonly class UpdateField implements Action
{
    /**
     * @param array<mixed>|null $translations
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Pattern(Field::KEY_PATTERN)]
        public string $key,
        public ?FieldType $type = null,
        #[Description('Changes by language, e.g. {"de": {"label": "Telefon"}}: texts left out stay, null removes a language')]
        public ?array $translations = null,
        public ?bool $required = null,
        #[Description('A new name of the value; entries sent before keep the old one')]
        #[Pattern(Field::KEY_PATTERN)]
        public ?string $newKey = null,
    ) {}

    public function handle(FormEditor $editor, FormStore $store): FormDetails
    {
        $action = 'frootbox/forms/form.field-update';
        $current = $store->current($this->id);
        $field = $editor->fieldOf($action, $current, $this->key);

        $changed = new Field(
            $this->newKey ?? $field->key,
            $this->type ?? $field->type,
            $this->required ?? $field->required,
            null === $this->translations ? $field->translations : $editor->fieldTexts($action, $this->translations, $field->translations),
        );

        return $editor->saveDraft($this->id, $editor->replaceField($action, $current, $this->key, $changed));
    }
}

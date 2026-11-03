<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Action<FormDetails>
 */
#[AsAction('frootbox/forms/form.field-remove', 'Removes a field from the draft of a form; entries sent before keep its value', permission: 'frootbox/forms/form.update')]
final readonly class RemoveField implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Pattern(Field::KEY_PATTERN)]
        public string $key,
    ) {}

    public function handle(FormEditor $editor, FormStore $store): FormDetails
    {
        return $editor->saveDraft($this->id, $editor->removeField('frootbox/forms/form.field-remove', $store->current($this->id), $this->key));
    }
}

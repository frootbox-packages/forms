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
#[AsAction('frootbox/forms/form.field-move', 'Moves a field of a form to another position', permission: 'frootbox/forms/form.update')]
final readonly class MoveField implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Pattern(Field::KEY_PATTERN)]
        public string $key,
        #[Description('The new position, from 0')]
        #[Range(min: 0)]
        public int $position,
    ) {}

    public function handle(FormEditor $editor, FormStore $store): FormDetails
    {
        return $editor->saveDraft($this->id, $editor->moveField('frootbox/forms/form.field-move', $store->current($this->id), $this->key, $this->position));
    }
}

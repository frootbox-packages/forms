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
 * @implements Action<null>
 */
#[AsAction('frootbox/forms/form.delete', 'Deletes a form with its draft and its entries; blocks showing it show nothing then')]
final readonly class DeleteForm implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(FormEditor $editor): null
    {
        $editor->delete($this->id);

        return null;
    }
}

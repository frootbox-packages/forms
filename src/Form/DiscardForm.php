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
 * @implements Action<FormDetails|null>
 */
#[AsAction('frootbox/forms/form.discard', 'Throws away the draft of a form; a form never published is gone then')]
final readonly class DiscardForm implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(FormEditor $editor): ?FormDetails
    {
        return $editor->discard('frootbox/forms/form.discard', $this->id);
    }
}

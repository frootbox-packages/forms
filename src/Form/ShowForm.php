<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Query<FormDetails>
 */
#[AsQuery('frootbox/forms/form.show', 'Returns a form with its draft, its published version and the number of its entries')]
final readonly class ShowForm implements Query
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(FormStore $store): FormDetails
    {
        return $store->details($this->id);
    }
}

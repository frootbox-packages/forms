<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Query<Entry>
 */
#[AsQuery('frootbox/forms/entry.show', 'Returns one entry of a form with all its values', permission: 'frootbox/forms/entry.list')]
final readonly class ShowEntry implements Query
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(Entries $entries): Entry
    {
        return $entries->getById($this->id);
    }
}

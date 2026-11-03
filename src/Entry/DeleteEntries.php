<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\ListOf;

/**
 * @implements Action<int>
 */
#[AsAction('frootbox/forms/entry.delete', 'Deletes entries of forms; returns how many were deleted')]
final readonly class DeleteEntries implements Action
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[ListOf('string')]
        public array $ids,
    ) {}

    public function handle(Entries $entries): int
    {
        $deleted = 0;

        foreach (array_unique($this->ids) as $id) {
            $deleted += $entries->delete($id) ? 1 : 0;
        }

        return $deleted;
    }
}

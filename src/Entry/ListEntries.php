<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Database\Ulid;

/**
 * @implements Query<EntryList>
 */
#[AsQuery('frootbox/forms/entry.list', 'Lists what visitors sent with a form, newest first')]
final readonly class ListEntries implements Query
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $form,
        #[Range(min: 1)]
        public int $page = 1,
        #[Range(min: 1, max: 1000)]
        public int $perPage = 50,
    ) {}

    public function handle(Entries $entries): EntryList
    {
        $paginated = $entries->paginate(['where' => ['formId' => $this->form], 'order' => ['createdAt' => 'desc', 'id' => 'desc']], $this->page, $this->perPage);

        return new EntryList($paginated->items->all(), $paginated->total, $paginated->page, $paginated->pages());
    }
}

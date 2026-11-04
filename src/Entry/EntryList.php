<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

/**
 * A page of entries of a form, newest first, with the count of the
 * inbox and the spam folder.
 */
final readonly class EntryList
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $pages,
        /** Entries in the spam folder (decision 0261) */
        public int $spam = 0,
        /** Entries in the inbox */
        public int $inbox = 0,
    ) {}
}

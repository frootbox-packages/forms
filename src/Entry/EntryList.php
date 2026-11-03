<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

/**
 * A page of entries of a form, newest first.
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
    ) {}
}

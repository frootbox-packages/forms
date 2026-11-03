<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Entry>
 */
final class Entries extends Repository
{
    public const string TABLE = 'frootbox_forms_entries';

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Entry::class;
    }
}

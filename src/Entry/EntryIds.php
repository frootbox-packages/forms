<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Support\Message;

/**
 * The ids an action on entries gets must all name an entry. Otherwise
 * nothing happens and the action says so, instead of reporting success
 * for nothing, e.g. for an empty id from a dialog opened before its
 * script filled it in.
 */
final readonly class EntryIds
{
    /**
     * @param list<string> $ids
     *
     * @throws InvalidInputException With the pointer of each id without an entry
     */
    public static function check(Entries $entries, string $action, array $ids): void
    {
        $errors = [];

        foreach ($ids as $index => $id) {
            if ('' === $id || null === $entries->fetchById($id)) {
                $errors['/ids/' . $index] = new Message('There is no such entry.');
            }
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }
    }
}

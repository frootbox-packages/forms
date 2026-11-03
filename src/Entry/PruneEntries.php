<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use DateTimeImmutable;
use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Forms\FormsSettings;

/**
 * Deletes the entries older than the setting "Keep entries" (decision
 * 0222). The scheduler runs it as the system when there are some.
 *
 * @implements Action<int>
 */
#[AsAction('frootbox/forms/entry.prune', 'Deletes the entries of forms older than the setting "Keep entries"; returns how many', permission: 'frootbox/forms/entry.delete')]
final readonly class PruneEntries implements Action
{
    public function handle(Entries $entries, FormsSettings $settings): int
    {
        $before = self::before($settings);

        if (null === $before) {
            return 0;
        }

        $deleted = 0;

        foreach ($entries->fetch(['where' => ['createdAt <' => $before], 'limit' => 1000]) as $entry) {
            $deleted += $entries->delete($entry->id) ? 1 : 0;
        }

        return $deleted;
    }

    /**
     * Entries from before this time go, null if all stay.
     */
    public static function before(FormsSettings $settings, DateTimeImmutable $now = new DateTimeImmutable()): ?DateTimeImmutable
    {
        return 0 === $settings->keepDays ? null : $now->modify(\sprintf('-%d days', $settings->keepDays));
    }
}

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
 * 0222) and spam older than 30 days (decision 0261). The scheduler runs
 * it as the system when there are some.
 *
 * @implements Action<int>
 */
#[AsAction('frootbox/forms/entry.prune', 'Deletes the entries of forms older than the setting "Keep entries"; returns how many', permission: 'frootbox/forms/entry.delete')]
final readonly class PruneEntries implements Action
{
    /** Days spam stays, unless "Keep entries" is shorter. */
    public const int SPAM_DAYS = 30;

    public function handle(Entries $entries, FormsSettings $settings): int
    {
        $deleted = 0;

        foreach (self::where($settings) as $where) {
            foreach ($entries->fetch(['where' => $where, 'limit' => 1000]) as $entry) {
                $deleted += $entries->delete($entry->id) ? 1 : 0;
            }
        }

        return $deleted;
    }

    /**
     * What goes: old entries of any kind and older spam.
     *
     * @return list<array<string, mixed>>
     */
    public static function where(FormsSettings $settings, DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        $before = self::before($settings, $now);
        $spam = $now->modify(\sprintf('-%d days', self::SPAM_DAYS));

        return [
            ...(null === $before ? [] : [['createdAt <' => $before]]),
            ...(null !== $before && $before >= $spam ? [] : [['spam !=' => null, 'createdAt <' => $spam]]),
        ];
    }

    /**
     * Entries from before this time go, null if all stay.
     */
    public static function before(FormsSettings $settings, DateTimeImmutable $now = new DateTimeImmutable()): ?DateTimeImmutable
    {
        return 0 === $settings->keepDays ? null : $now->modify(\sprintf('-%d days', $settings->keepDays));
    }
}

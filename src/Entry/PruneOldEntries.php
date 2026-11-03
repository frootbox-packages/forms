<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Forms\FormsSettings;

/**
 * Listens to the scheduler and deletes old entries when there are some.
 */
final readonly class PruneOldEntries
{
    public function __construct(
        private Entries $entries,
        private FormsSettings $settings,
        private Runner $runner,
    ) {}

    public function __invoke(ScheduleRan $event): void
    {
        $before = PruneEntries::before($this->settings, $event->now);

        if (null !== $before && $this->entries->count(['createdAt <' => $before]) > 0) {
            $this->runner->run(new PruneEntries(), Context::system());
        }
    }
}

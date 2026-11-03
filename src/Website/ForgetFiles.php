<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Forms\Entry\Entries;

/**
 * Removes the files of deleted entries a day later, on a run of the
 * scheduler (decision 0230).
 */
final readonly class ForgetFiles
{
    public function __construct(
        private FormFiles $files,
        private Entries $entries,
    ) {}

    public function __invoke(ScheduleRan $event): void
    {
        $this->files->sweep($this->entries, $event->now->getTimestamp());
    }
}

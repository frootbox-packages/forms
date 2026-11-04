<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\Storage\FileStores;
use Frootbox\Core\Storage\StorageException;
use Frootbox\Core\Storage\StoreRole;
use Frootbox\Forms\Entry\Entries;

/**
 * Removes the files of deleted entries a day later, on a run of the
 * scheduler (decision 0230). A bucket is asked only once an hour, not on
 * every run (decision 0268).
 */
final readonly class ForgetFiles
{
    private const int BUCKET_EVERY = 3600;

    public function __construct(
        private FormFiles $files,
        private Entries $entries,
        private FileStores $stores,
        private ProjectPaths $paths,
    ) {}

    public function __invoke(ScheduleRan $event): void
    {
        $now = $event->now->getTimestamp();

        if ($this->stores->isRemote(StoreRole::Storage)) {
            $stamp = $this->paths->var('forms/sweep');

            if (is_file($stamp) && $now - (int) filemtime($stamp) < self::BUCKET_EVERY) {
                return;
            }

            if (is_dir(\dirname($stamp)) || @mkdir(\dirname($stamp), 0o775, true)) {
                @touch($stamp, $now);
            }
        }

        try {
            $this->files->sweep($this->entries, $now);
        } catch (StorageException) {
            // Next time.
        }
    }
}

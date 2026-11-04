<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Frootbox\Database\Values;

/**
 * The tasks of the proof of work that were solved (decision 0261), so a
 * solution counts once. Bookkeeping, written directly like the sendings.
 */
final readonly class Proofs
{
    public const string TABLE = 'frootbox_forms_proofs';

    /** A task is valid an hour, so it is kept a bit longer. */
    private const int KEEP = 7200;

    public function __construct(private Connection $connection) {}

    /**
     * Uses up a solved task; false if it was used before.
     */
    public function use(string $task, DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE used_at < ?',
            [$now->modify(\sprintf('-%d seconds', self::KEEP))->format(Values::DATE_FORMAT)],
        );

        try {
            $this->connection->insert(self::TABLE, ['key_hash' => hash('sha256', $task), 'used_at' => $now->format(Values::DATE_FORMAT)]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}

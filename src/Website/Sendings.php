<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Frootbox\Core\Security\Secrets;
use Frootbox\Database\Values;

/**
 * Counts sendings per address and form for the spam protection (decision
 * 0222): at most 5 in 10 minutes. The table keeps a keyed hash of the
 * address and the form, nothing older than an hour. Bookkeeping, written directly
 * like the failed sign-ins.
 */
final readonly class Sendings
{
    public const string TABLE = 'frootbox_forms_sendings';

    public const int LIMIT = 5;

    public const int WINDOW = 600;

    private const int KEEP = 3600;

    public function __construct(
        private Connection $connection,
        private Secrets $secrets,
    ) {}

    /**
     * Seconds to wait before this address may send the form again, 0 if
     * it may now.
     */
    public function wait(string $formId, ?string $address, DateTimeImmutable $now = new DateTimeImmutable()): int
    {
        if (null === $address) {
            return 0;
        }

        $times = $this->connection->createQueryBuilder()
            ->select('sent_at')
            ->from(self::TABLE)
            ->where('key_hash = ?')
            ->andWhere('sent_at > ?')
            ->orderBy('sent_at')
            ->setParameters([$this->key($formId, $address), $now->modify(\sprintf('-%d seconds', self::WINDOW))->format(Values::DATE_FORMAT)])
            ->executeQuery()
            ->fetchFirstColumn();

        if (\count($times) < self::LIMIT) {
            return 0;
        }

        $first = $times[\count($times) - self::LIMIT];

        return \is_string($first) ? max(1, new DateTimeImmutable($first)->getTimestamp() + self::WINDOW - $now->getTimestamp()) : 0;
    }

    /**
     * Counts a sending and forgets those older than an hour.
     */
    public function count(string $formId, ?string $address, DateTimeImmutable $now = new DateTimeImmutable()): void
    {
        if (null === $address) {
            return;
        }

        $this->connection->insert(self::TABLE, ['key_hash' => $this->key($formId, $address), 'sent_at' => $now->format(Values::DATE_FORMAT)]);
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE sent_at < ?',
            [$now->modify(\sprintf('-%d seconds', self::KEEP))->format(Values::DATE_FORMAT)],
        );
    }

    /**
     * Keyed with APP_SECRET where there is one, so the address cannot be
     * found again by trying all of them.
     */
    private function key(string $formId, string $address): string
    {
        $text = $formId . '|' . $address;

        return $this->secrets->sign('frootbox/forms/sendings', $text) ?? hash('sha256', 'frootbox/forms|' . $text);
    }
}

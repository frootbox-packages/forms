<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Security\Secrets;

/**
 * The signed time a form was shown (decision 0222). Sent faster than a
 * person can fill it in, it counts as a bot. Without APP_SECRET there is
 * nothing to sign and every token passes.
 */
final readonly class FormToken
{
    /** Seconds a person needs at least. */
    public const int MIN_SECONDS = 3;

    private const string PURPOSE = 'frootbox/forms/shown';

    public function __construct(private Secrets $secrets) {}

    public function issue(string $formId, ?int $now = null): string
    {
        $time = (string) ($now ?? time());

        return $time . '.' . ($this->secrets->sign(self::PURPOSE, $formId . '|' . $time) ?? '');
    }

    /**
     * Whether a person may have sent the form with this token.
     */
    public function passes(string $formId, ?string $token, ?int $now = null): bool
    {
        if (!$this->secrets->available()) {
            return true;
        }

        if (null === $token || 1 !== preg_match('/^(\d{1,12})\.([A-Za-z0-9_-]{20,100})$/', $token, $match)) {
            return false;
        }

        $age = ($now ?? time()) - (int) $match[1];

        return $age >= self::MIN_SECONDS && $this->secrets->verify(self::PURPOSE, $formId . '|' . $match[1], $match[2]);
    }
}

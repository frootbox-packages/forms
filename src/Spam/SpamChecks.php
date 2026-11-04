<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

/**
 * A rule for the content of entries from an extension (decision 0261).
 */
final readonly class SpamChecks
{
    /**
     * @param class-string<SpamCheck> $check
     */
    public function __construct(public string $check) {}
}

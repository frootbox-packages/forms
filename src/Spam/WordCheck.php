<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

use Frootbox\Forms\FormsSettings;

/**
 * A word of the setting "Words of spam" in an entry is suspicious
 * (decision 0261). Whole words only, any case.
 */
final readonly class WordCheck implements SpamCheck
{
    public function __construct(private FormsSettings $settings) {}

    public function check(SpamSubject $subject): ?string
    {
        $words = array_values(array_filter(array_map(trim(...), explode(',', $this->settings->spamWords)), static fn(string $word): bool => '' !== $word));

        if ([] === $words) {
            return null;
        }

        $pattern = '/(?<![\p{L}\p{N}])(?:' . implode('|', array_map(static fn(string $word): string => preg_quote($word, '/'), $words)) . ')(?![\p{L}\p{N}])/iu';

        return 1 === preg_match($pattern, $subject->text()) ? 'A word of spam from the settings.' : null;
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

use Frootbox\Forms\FormsSettings;

/**
 * Spam brings links: more than the setting "Links in an entry" is
 * suspicious (decision 0261).
 */
final readonly class LinkCheck implements SpamCheck
{
    public function __construct(private FormsSettings $settings) {}

    public function check(SpamSubject $subject): ?string
    {
        $links = preg_match_all('~\b(?:https?://|www\.)|\[url[=\]]|<a\s~i', $subject->text());

        return $links > $this->settings->maxLinks ? 'More links than the setting allows.' : null;
    }
}

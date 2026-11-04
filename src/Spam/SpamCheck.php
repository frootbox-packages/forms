<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

/**
 * A rule for the content of an entry (decision 0261), registered with
 *
 *     $registry->add(SpamChecks::class, new SpamChecks(AcmeCheck::class));
 *
 * and loaded from the container.
 */
interface SpamCheck
{
    /**
     * Why the entry looks like spam, in English for the backend, or null.
     */
    public function check(SpamSubject $subject): ?string;
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

/**
 * What a visitor reads after sending a form. Spam gets the same answer.
 */
final readonly class SubmitResult
{
    public function __construct(
        /** The text after sending of the form, empty for a general thanks */
        public string $message,
    ) {}
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\Support\Message;

/**
 * The result of a captcha: passed, wrong with a message for the visitor
 * or suspicious with a reason for the spam folder.
 */
final readonly class CaptchaResult
{
    private function __construct(
        public Verdict $verdict,
        /** For the visitor if wrong, the reason in English if suspicious */
        public ?Message $message = null,
    ) {}

    public static function passed(): self
    {
        return new self(Verdict::Passed);
    }

    public static function wrong(Message $message): self
    {
        return new self(Verdict::Wrong, $message);
    }

    /**
     * @param string $reason In English, translated in the backend, e.g. "The proof of work is missing."
     */
    public static function suspicious(string $reason): self
    {
        return new self(Verdict::Suspicious, new Message($reason));
    }
}

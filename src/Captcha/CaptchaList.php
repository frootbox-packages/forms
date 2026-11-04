<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * The captchas there are and the one forms use by standard.
 */
final readonly class CaptchaList
{
    /**
     * @param list<array{name: string, title: string, description: string, service: string|null, ready: bool}> $captchas
     */
    public function __construct(
        public array $captchas,
        /** A name of the list or "none" */
        public string $standard,
    ) {}
}

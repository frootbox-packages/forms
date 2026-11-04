<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * The form a captcha is shown in or checked for.
 */
final readonly class CaptchaRequest
{
    public function __construct(
        public string $formId,
        /** The language of the form for the visitor */
        public string $language,
        /** The address of the visitor, null if unknown */
        public ?string $address = null,
    ) {}
}

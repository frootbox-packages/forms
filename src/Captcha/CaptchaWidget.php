<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * The markup of a captcha in a form. With a service it is wrapped in
 * external() and loads after consent (decision 0224); its scripts may
 * then come from the provider.
 */
final readonly class CaptchaWidget
{
    public function __construct(
        /** HTML, escaped by the provider */
        public string $html,
        /** The external service, as registered with ExternalService */
        public ?string $service = null,
        /** What the placeholder is about, e.g. "Spam protection" */
        public ?string $title = null,
    ) {}
}

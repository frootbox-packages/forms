<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * A captcha a form can choose (decision 0261), registered by the
 * extension that brings it:
 *
 *     $registry->add(Captcha::class, new Captcha(
 *         'acme/captcha/turnstile', 'Cloudflare Turnstile', TurnstileCaptcha::class,
 *         service: 'acme/captcha/turnstile',
 *     ));
 *
 * With a service its markup loads only after the visitor agrees
 * (decision 0224); the service is registered as ExternalService.
 */
final readonly class Captcha
{
    /**
     * @param class-string<CaptchaProvider> $provider
     */
    public function __construct(
        /** After the package, e.g. "acme/captcha/turnstile" (decision 0091) */
        public string $name,
        /** The name editors know, translated in the backend */
        public string $title,
        public string $provider,
        /** The external service whose consent it needs, if any */
        public ?string $service = null,
        /** What visitors notice, for the choice in the backend */
        public string $description = '',
    ) {}
}

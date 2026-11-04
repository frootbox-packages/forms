<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * A captcha that needs setting up first, e.g. with the keys of its
 * provider (decision 0262). Until it is ready no form uses it: the
 * backend shows it greyed out and a form that chose it gets the proof
 * of work.
 */
interface ConfigurableCaptcha extends CaptchaProvider
{
    public function ready(): bool;
}

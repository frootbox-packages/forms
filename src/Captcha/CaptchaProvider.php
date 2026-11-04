<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * What a captcha does (decision 0261): its markup in the form and the
 * check on the server. What it gives in the browser comes back in fields
 * named "captcha[…]", as the input "captcha" of frootbox/forms/form.submit.
 */
interface CaptchaProvider
{
    /**
     * The markup inside the form, before the button. Pages come from the
     * cache, so it is the same for every visitor until the page changes.
     */
    public function widget(CaptchaRequest $request): CaptchaWidget;

    /**
     * @param array<string, string> $answer The fields "captcha[…]" by name
     */
    public function verify(CaptchaRequest $request, array $answer): CaptchaResult;
}

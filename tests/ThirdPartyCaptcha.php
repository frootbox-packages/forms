<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Core\Support\Message;
use Frootbox\Forms\Captcha\CaptchaProvider;
use Frootbox\Forms\Captcha\CaptchaRequest;
use Frootbox\Forms\Captcha\CaptchaResult;
use Frootbox\Forms\Captcha\CaptchaWidget;

/**
 * A captcha of another provider for the tests: a script from its server
 * that puts a token into the form.
 */
final readonly class ThirdPartyCaptcha implements CaptchaProvider
{
    public function widget(CaptchaRequest $request): CaptchaWidget
    {
        return new CaptchaWidget('<script src="https://acme.example/checkbox.js"></script><input type="hidden" name="captcha[token]">');
    }

    public function verify(CaptchaRequest $request, array $answer): CaptchaResult
    {
        return 'passed' === ($answer['token'] ?? null) ? CaptchaResult::passed() : CaptchaResult::wrong(new Message('Please load the spam protection first.'));
    }
}

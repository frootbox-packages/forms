<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

/**
 * What a check of an entry found (decision 0261).
 */
enum Verdict: string
{
    /** Goes on as usual */
    case Passed = 'passed';
    /** The visitor sees an error at the captcha and may try again */
    case Wrong = 'wrong';
    /** Kept in the spam folder without a mail; the visitor sees the thanks */
    case Suspicious = 'suspicious';
}

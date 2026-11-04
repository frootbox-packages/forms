<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms;

use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Action\Schema\Title;
use Frootbox\Core\Settings\AsSettings;

/**
 * The settings of the forms (decisions 0222, 0261).
 */
#[AsSettings('Forms', 'Entries of the forms on the website and their protection against spam')]
final readonly class FormsSettings
{
    public function __construct(
        #[Title('Keep entries'), Description('Days an entry stays in the backend before it is deleted; 0 keeps them until someone deletes them.')]
        #[Range(min: 0, max: 3650)]
        public int $keepDays = 180,
        #[Title('Captcha'), Description('The captcha of forms that choose none of their own: frootbox/forms/proof-of-work (invisible), frootbox/forms/math (a sum to solve), the name of one from another extension or none.')]
        #[Length(max: 100)]
        public string $captcha = 'frootbox/forms/proof-of-work',
        #[Title('Links in an entry'), Description('An entry with more links goes to the spam folder.')]
        #[Range(min: 0, max: 100)]
        public int $maxLinks = 3,
        #[Title('Words of spam'), Description('An entry with one of these words goes to the spam folder, separated by commas, e.g. "casino, crypto".')]
        #[Length(max: 2000)]
        public string $spamWords = '',
    ) {}
}

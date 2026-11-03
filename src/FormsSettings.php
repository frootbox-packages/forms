<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms;

use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Action\Schema\Title;
use Frootbox\Core\Settings\AsSettings;

/**
 * The settings of the forms (decision 0222).
 */
#[AsSettings('Forms', 'Entries of the forms on the website')]
final readonly class FormsSettings
{
    public function __construct(
        #[Title('Keep entries'), Description('Days an entry stays in the backend before it is deleted; 0 keeps them until someone deletes them.')]
        #[Range(min: 0, max: 3650)]
        public int $keepDays = 180,
    ) {}
}

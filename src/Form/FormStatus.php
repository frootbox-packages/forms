<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

enum FormStatus: string
{
    /** Never published */
    case Draft = 'draft';
    /** Online without changes */
    case Published = 'published';
    /** Online with a draft of changes */
    case Changed = 'changed';
}

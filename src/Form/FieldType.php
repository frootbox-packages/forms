<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * The types of fields in a form (decision 0222).
 */
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Textarea = 'textarea';
    case Select = 'select';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Files = 'files';

    /**
     * Whether visitors choose from options the editor gives.
     */
    public function hasOptions(): bool
    {
        return \in_array($this, [self::Select, self::Radio, self::Checkboxes], true);
    }

    /**
     * The name for editors, translated in the backend.
     */
    public function title(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Textarea => 'Long text',
            self::Select => 'Drop-down list',
            self::Radio => 'One of several',
            self::Checkboxes => 'Several of several',
            self::Checkbox => 'Checkbox',
            self::Consent => 'Consent',
            self::Files => 'Files',
        };
    }

    /**
     * The most characters a value may have.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Textarea => 10000,
            self::Email, self::Phone, self::Number, self::Date => 254,
            default => 500,
        };
    }
}

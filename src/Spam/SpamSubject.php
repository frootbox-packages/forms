<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

/**
 * What a rule for the content looks at: the values a visitor sent.
 */
final readonly class SpamSubject
{
    /**
     * @param list<array{key: string, label: string, type: string, value: string|list<string>|bool}> $fields
     */
    public function __construct(
        public string $formId,
        public string $language,
        public array $fields,
    ) {}

    /**
     * The texts of all fields, one per line.
     */
    public function text(): string
    {
        $texts = [];

        foreach ($this->fields as $field) {
            $value = $field['value'];
            $texts[] = \is_array($value) ? implode("\n", $value) : (\is_string($value) ? $value : '');
        }

        return implode("\n", $texts);
    }
}

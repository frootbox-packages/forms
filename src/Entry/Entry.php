<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * What a visitor sent with a form. The fields keep the labels of the
 * moment, so an entry stays readable when the form changes later.
 */
final readonly class Entry extends Entity
{
    /**
     * @param list<array{key: string, label: string, type: string, value: string|list<string>|bool, files?: list<array{id: string, name: string, size: int}>}> $fields The values with the labels of the moment
     */
    public function __construct(
        public string $id,
        public string $formId,
        public string $language,
        public array $fields,
        public ?string $pageId,
        public DateTimeImmutable $createdAt,
        public ?string $about = null,
        public ?string $aboutTitle = null,
    ) {}

    /**
     * The value of a field as text, e.g. for the list or the export.
     *
     * @param string|list<string>|bool $value
     */
    public static function text(string|array|bool $value, string $yes = 'yes', string $no = 'no'): string
    {
        return match (true) {
            \is_bool($value) => $value ? $yes : $no,
            \is_array($value) => implode(', ', $value),
            default => $value,
        };
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * What a form is, as stored in its draft or published version: texts by
 * language, who gets the entries by mail and the fields in their order.
 */
final readonly class FormData
{
    /**
     * @param array<string, FormText> $translations
     * @param list<string>            $recipients
     * @param list<Field>             $fields
     */
    public function __construct(
        public array $translations = [],
        public array $recipients = [],
        public array $fields = [],
    ) {}

    /**
     * The texts in a language, else in the first one there is.
     */
    public function text(string $language): FormText
    {
        return $this->translations[$language] ?? array_values($this->translations)[0] ?? new FormText('');
    }

    /**
     * The language whose texts show for a visitor: theirs, else the first.
     */
    public function language(string $language): ?string
    {
        return isset($this->translations[$language]) ? $language : array_key_first($this->translations);
    }

    public function field(string $key): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, FormText>|null $translations
     * @param list<string>|null            $recipients
     * @param list<Field>|null             $fields
     */
    public function with(?array $translations = null, ?array $recipients = null, ?array $fields = null): self
    {
        return new self($translations ?? $this->translations, $recipients ?? $this->recipients, $fields ?? $this->fields);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $translations = [];

        foreach (\is_array($data['translations'] ?? null) ? $data['translations'] : [] as $language => $text) {
            if (\is_array($text)) {
                $translations[(string) $language] = FormText::fromArray($text);
            }
        }

        $fields = [];

        foreach (\is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            if (\is_array($field)) {
                $fields[] = Field::fromArray($field);
            }
        }

        return new self(
            $translations,
            \is_array($data['recipients'] ?? null) ? array_values(array_filter($data['recipients'], \is_string(...))) : [],
            $fields,
        );
    }

    /**
     * @return array{translations: array<string, array{title: string, button: string, success: string}>, recipients: list<string>, fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'translations' => array_map(static fn(FormText $text): array => $text->toArray(), $this->translations),
            'recipients' => $this->recipients,
            'fields' => array_map(static fn(Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}

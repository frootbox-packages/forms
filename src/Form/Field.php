<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * A field of a form. The key names the value in entries and stays when
 * the label changes.
 */
final readonly class Field
{
    public const string KEY_PATTERN = '^[a-z][a-zA-Z0-9]{0,39}$';

    /**
     * @param array<string, FieldText> $translations
     */
    public function __construct(
        public string $key,
        public FieldType $type,
        public bool $required = false,
        public array $translations = [],
    ) {}

    /**
     * Consent is always required: without it the form is not sent.
     */
    public function isRequired(): bool
    {
        return $this->required || FieldType::Consent === $this->type;
    }

    /**
     * The texts in a language, else in the first one there is.
     */
    public function text(string $language): FieldText
    {
        return $this->translations[$language] ?? array_values($this->translations)[0] ?? new FieldText('');
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $translations = [];

        foreach (\is_array($data['translations'] ?? null) ? $data['translations'] : [] as $language => $text) {
            if (\is_array($text)) {
                $translations[(string) $language] = FieldText::fromArray($text);
            }
        }

        return new self(
            \is_string($data['key'] ?? null) ? $data['key'] : '',
            FieldType::tryFrom(\is_string($data['type'] ?? null) ? $data['type'] : '') ?? FieldType::Text,
            true === ($data['required'] ?? false),
            $translations,
        );
    }

    /**
     * @return array{key: string, type: string, required: bool, translations: array<string, array{label: string, hint: string, options: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'required' => $this->required,
            'translations' => array_map(static fn(FieldText $text): array => $text->toArray(), $this->translations),
        ];
    }
}

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
        /** For consent: where the label links to, e.g. "page:01J…" for the privacy policy (decision 0259). */
        public ?string $link = null,
    ) {}

    /**
     * The label in a language without the brackets that mark the link, for
     * mails, entries and the search.
     */
    public function label(string $language): string
    {
        return str_replace(['[', ']'], '', $this->text($language)->label);
    }

    /**
     * The label in three parts around the text in square brackets that
     * becomes the link, e.g. "I have read the [privacy policy].". Null
     * without brackets: then the theme puts a link after the label.
     *
     * @return array{string, string, string}|null
     */
    public function linkParts(string $language): ?array
    {
        if (1 !== preg_match('/^(.*?)\[([^\[\]]+)\](.*)$/su', $this->text($language)->label, $match)) {
            return null;
        }

        return [str_replace(['[', ']'], '', $match[1]), $match[2], str_replace(['[', ']'], '', $match[3])];
    }

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
            \is_string($data['link'] ?? null) && '' !== $data['link'] ? $data['link'] : null,
        );
    }

    /**
     * @return array{key: string, type: string, required: bool, translations: array<string, array{label: string, hint: string, options: list<string>}>, link?: string}
     */
    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'type' => $this->type->value,
            'required' => $this->required,
            'translations' => array_map(static fn(FieldText $text): array => $text->toArray(), $this->translations),
        ];

        if (null !== $this->link) {
            $data['link'] = $this->link;
        }

        return $data;
    }
}

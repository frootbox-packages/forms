<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use DateTimeImmutable;
use Frootbox\Core\Support\Message;
use Frootbox\Forms\Form\Field;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\FormData;

/**
 * Checks what a visitor sent against the fields of a form, in the
 * language they saw it in.
 */
final class Submission
{
    /** @var list<array{key: string, label: string, type: string, value: string|list<string>|bool, files?: list<array{id: string, name: string, size: int}>}> */
    public private(set) array $fields = [];

    /** @var array<string, Message> by JSON pointer */
    public private(set) array $errors = [];

    /** @var array<string, list<string>> The uploads of each field with files, by key (decision 0230) */
    public private(set) array $uploads = [];

    /**
     * @param array<mixed> $values by key of the field; for files the tokens of the uploads
     */
    public function __construct(FormData $form, string $language, array $values, private readonly ?FormFiles $files = null)
    {
        foreach ($form->fields as $field) {
            $value = $this->value($field, $field->text($language)->options, $values[$field->key] ?? null);

            if (null !== $value) {
                $this->fields[] = ['key' => $field->key, 'label' => $field->label($language), 'type' => $field->type->value, 'value' => $value];
            }
        }
    }

    /**
     * The first email a visitor gave, for answering them.
     */
    public function replyTo(): ?string
    {
        foreach ($this->fields as $field) {
            if (FieldType::Email->value === $field['type'] && \is_string($field['value']) && '' !== $field['value']) {
                return $field['value'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $options
     *
     * @return string|list<string>|bool|null null after an error
     */
    private function value(Field $field, array $options, mixed $raw): string|array|bool|null
    {
        $at = '/values/' . $field->key;

        if (FieldType::Files === $field->type) {
            return $this->files($field, $raw, $at);
        }

        if (\in_array($field->type, [FieldType::Checkbox, FieldType::Consent], true)) {
            $checked = true === $raw || (\is_string($raw) && \in_array($raw, ['1', 'on', 'true', 'yes'], true));

            if (!$checked && $field->isRequired()) {
                $this->errors[$at] = new Message(FieldType::Consent === $field->type ? 'Please agree to send the form.' : 'Please check this box.');

                return null;
            }

            return $checked;
        }

        if (FieldType::Checkboxes === $field->type) {
            $chosen = \is_array($raw) ? array_values(array_filter($raw, \is_string(...))) : (\is_string($raw) && '' !== $raw ? [$raw] : []);
            $chosen = array_values(array_intersect($options, $chosen));

            if ([] === $chosen && $field->isRequired()) {
                $this->errors[$at] = new Message('Please choose at least one.');

                return null;
            }

            return $chosen;
        }

        if (null !== $raw && !\is_string($raw) && !\is_int($raw) && !\is_float($raw)) {
            $this->errors[$at] = new Message('must be a text.');

            return null;
        }

        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $raw));

        if (FieldType::Textarea !== $field->type) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        }

        if ('' === $text) {
            if ($field->isRequired()) {
                $this->errors[$at] = new Message('Please fill in this field.');

                return null;
            }

            return '';
        }

        if (mb_strlen($text) > $field->type->maxLength()) {
            $this->errors[$at] = new Message('Please write at most {count} characters.', ['count' => $field->type->maxLength()]);

            return null;
        }

        $valid = match ($field->type) {
            FieldType::Email => false !== filter_var($text, \FILTER_VALIDATE_EMAIL),
            FieldType::Phone => 1 === preg_match('/^\+?[0-9 ()\/.\-]{3,40}$/', $text) && preg_match_all('/\d/', $text) >= 3,
            FieldType::Number => is_numeric(str_replace(',', '.', $text)),
            FieldType::Date => self::isDate($text),
            FieldType::Select, FieldType::Radio => \in_array($text, $options, true),
            default => true,
        };

        if (!$valid) {
            $this->errors[$at] = match ($field->type) {
                FieldType::Email => new Message('Please enter an email address, e.g. name@example.com.'),
                FieldType::Phone => new Message('Please enter a phone number.'),
                FieldType::Number => new Message('Please enter a number.'),
                FieldType::Date => new Message('Please enter a date.'),
                default => new Message('Please choose one of the options.'),
            };

            return null;
        }

        return $text;
    }

    /**
     * Checks the uploads of a field with files and keeps their tokens.
     *
     * @return list<string>|null The names of the files, null after an error
     */
    private function files(Field $field, mixed $raw, string $at): ?array
    {
        $tokens = \is_array($raw) ? array_values(array_filter($raw, static fn(mixed $token): bool => \is_string($token) && '' !== $token)) : (\is_string($raw) && '' !== $raw ? [$raw] : []);

        if ([] === $tokens) {
            if ($field->isRequired()) {
                $this->errors[$at] = new Message('Please choose a file.');

                return null;
            }

            return [];
        }

        if (\count($tokens) > FormFiles::MOST) {
            $this->errors[$at] = new Message('Please choose at most {count} files.', ['count' => FormFiles::MOST]);

            return null;
        }

        $names = [];
        $total = 0;

        foreach ($tokens as $token) {
            $file = null === $this->files ? null : $this->files->inspect($token);

            if (null === $file) {
                $this->errors[$at] = FormFiles::BROKEN === $token
                    ? new Message('A file could not be received. Each may have at most {max} MB.', ['max' => FormFiles::MAX_MB])
                    : new Message('Please choose PDF, Word, OpenDocument or text documents or images (JPEG, PNG, WebP).');

                return null;
            }

            if ($file['size'] > FormFiles::MAX_MB * 1024 * 1024) {
                $this->errors[$at] = new Message('The file {name} is too large, at most {max} MB.', ['name' => $file['name'], 'max' => FormFiles::MAX_MB]);

                return null;
            }

            $total += $file['size'];
            $names[] = $file['name'];
        }

        if ($total > FormFiles::TOTAL_MB * 1024 * 1024) {
            $this->errors[$at] = new Message('The files are too large together, at most {max} MB.', ['max' => FormFiles::TOTAL_MB]);

            return null;
        }

        $this->uploads[$field->key] = $tokens;

        return $names;
    }

    private static function isDate(string $text): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        return false !== $date && $date->format('Y-m-d') === $text;
    }
}

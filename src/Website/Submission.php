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
    /** @var list<array{key: string, label: string, type: string, value: string|list<string>|bool}> */
    public private(set) array $fields = [];

    /** @var array<string, Message> by JSON pointer */
    public private(set) array $errors = [];

    /**
     * @param array<mixed> $values by key of the field
     */
    public function __construct(FormData $form, string $language, array $values)
    {
        foreach ($form->fields as $field) {
            $value = $this->value($field, $field->text($language)->options, $values[$field->key] ?? null);

            if (null !== $value) {
                $this->fields[] = ['key' => $field->key, 'label' => $field->text($language)->label, 'type' => $field->type->value, 'value' => $value];
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

    private static function isDate(string $text): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        return false !== $date && $date->format('Y-m-d') === $text;
    }
}

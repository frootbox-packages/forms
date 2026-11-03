<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\Slug;
use Frootbox\Core\Revision\RevisionStore;
use Frootbox\Core\Support\Message;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Forms\Entry\Entries;

/**
 * Changes forms (decision 0222): every change goes into the draft, the
 * website shows the published version. The actions of the forms call it.
 */
final readonly class FormEditor
{
    public const int TITLE_LENGTH = 200;

    public const int BUTTON_LENGTH = 60;

    public const int SUCCESS_LENGTH = 1000;

    public const int LABEL_LENGTH = 200;

    public const int HINT_LENGTH = 500;

    public const int MAX_RECIPIENTS = 10;

    public const int MAX_FIELDS = 50;

    public const int MAX_OPTIONS = 50;

    public function __construct(
        private FormStore $store,
        private RevisionStore $revisions,
        private Languages $languages,
        private Entries $entries,
    ) {}

    /**
     * Saves a draft. Drafts may be unfinished, e.g. without fields; the
     * rest is checked when publishing (decision 0101).
     *
     * @throws EntityNotFoundException
     */
    public function saveDraft(string $id, FormData $data): FormDetails
    {
        $this->revisions->saveDraft(FormStore::TYPE, $id, $data->toArray());

        return $this->store->details($id);
    }

    /**
     * Applies texts by language to the current ones. A language set to
     * null is removed; texts left out are kept.
     *
     *     {"de": {"title": "Kontakt", "button": "Absenden", "success": "Danke!"}, "en": null}
     *
     * @param array<mixed>            $input
     * @param array<string, FormText> $current
     *
     * @return array<string, FormText>
     *
     * @throws InvalidInputException
     */
    public function translations(string $action, array $input, array $current = []): array
    {
        $errors = [];

        foreach ($this->byLanguage($action, $input, '/translations') as $language => $value) {
            $at = '/translations/' . $language;

            if (null === $value) {
                unset($current[$language]);

                continue;
            }

            $before = $current[$language] ?? new FormText('');

            $current[$language] = new FormText(
                $this->text($value, 'title', $before->title, self::TITLE_LENGTH, $at, $errors),
                $this->text($value, 'button', $before->button, self::BUTTON_LENGTH, $at, $errors),
                $this->text($value, 'success', $before->success, self::SUCCESS_LENGTH, $at, $errors),
            );
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $current;
    }

    /**
     * Checks the addresses that get the entries by mail.
     *
     * @param list<string> $recipients
     *
     * @return list<string>
     *
     * @throws InvalidInputException
     */
    public function recipients(string $action, array $recipients): array
    {
        $errors = [];
        $clean = [];

        foreach ($recipients as $index => $recipient) {
            $recipient = trim($recipient);

            if ('' === $recipient) {
                continue;
            }

            if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                $errors['/recipients/' . $index] = new Message('"{address}" is no email address.', ['address' => $recipient]);
            }

            $clean[mb_strtolower($recipient)] = $recipient;
        }

        if (\count($clean) > self::MAX_RECIPIENTS) {
            $errors['/recipients'] = new Message('may have at most {count} addresses.', ['count' => self::MAX_RECIPIENTS]);
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return array_values($clean);
    }

    /**
     * Applies the texts of a field by language: label, hint and options.
     *
     * @param array<mixed>             $input
     * @param array<string, FieldText> $current
     *
     * @return array<string, FieldText>
     *
     * @throws InvalidInputException
     */
    public function fieldTexts(string $action, array $input, array $current = []): array
    {
        $errors = [];

        foreach ($this->byLanguage($action, $input, '/translations') as $language => $value) {
            $at = '/translations/' . $language;

            if (null === $value) {
                unset($current[$language]);

                continue;
            }

            $before = $current[$language] ?? new FieldText('');
            $options = $before->options;

            if (\array_key_exists('options', $value)) {
                $options = [];

                if (!\is_array($value['options']) || !array_is_list($value['options'])) {
                    $errors[$at . '/options'] = new Message('must be a list of texts, e.g. ["Coffee", "Tea"].');
                } else {
                    foreach ($value['options'] as $index => $option) {
                        $option = \is_string($option) ? trim($option) : null;

                        if (null === $option || mb_strlen($option) > self::LABEL_LENGTH) {
                            $errors[$at . '/options/' . $index] = new Message('must be a text of at most {count} characters.', ['count' => self::LABEL_LENGTH]);
                        } elseif ('' !== $option && !\in_array($option, $options, true)) {
                            $options[] = $option;
                        }
                    }

                    if (\count($options) > self::MAX_OPTIONS) {
                        $errors[$at . '/options'] = new Message('may have at most {count} options.', ['count' => self::MAX_OPTIONS]);
                    }
                }
            }

            $current[$language] = new FieldText(
                $this->text($value, 'label', $before->label, self::LABEL_LENGTH, $at, $errors),
                $this->text($value, 'hint', $before->hint, self::HINT_LENGTH, $at, $errors),
                $options,
            );
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $current;
    }

    /**
     * A field added to the form, at the end or at a position from 0.
     *
     * @param array<string, FieldText> $translations
     *
     * @throws InvalidInputException
     */
    public function addField(string $action, FormData $data, ?string $key, FieldType $type, bool $required, array $translations, ?int $position = null): FormData
    {
        if (\count($data->fields) >= self::MAX_FIELDS) {
            throw new InvalidInputException($action, ['/key' => new Message('A form has at most {count} fields.', ['count' => self::MAX_FIELDS])]);
        }

        $key = null === $key || '' === $key ? $this->keyFor($data, $translations) : $key;
        $this->checkKey($action, $data, $key);

        $fields = $data->fields;
        array_splice($fields, null === $position ? \count($fields) : max(0, min($position, \count($fields))), 0, [new Field($key, $type, $required, $translations)]);

        return $data->with(fields: $fields);
    }

    /**
     * The form with one field replaced.
     *
     * @throws InvalidInputException
     */
    public function replaceField(string $action, FormData $data, string $key, Field $field): FormData
    {
        $this->fieldOf($action, $data, $key);

        if ($field->key !== $key) {
            $this->checkKey($action, $data, $field->key, '/newKey');
        }

        return $data->with(fields: array_map(static fn(Field $each): Field => $each->key === $key ? $field : $each, $data->fields));
    }

    /**
     * The form with a field at another position, from 0.
     *
     * @throws InvalidInputException
     */
    public function moveField(string $action, FormData $data, string $key, int $position): FormData
    {
        $field = $this->fieldOf($action, $data, $key);
        $fields = array_values(array_filter($data->fields, static fn(Field $each): bool => $each->key !== $key));
        array_splice($fields, max(0, min($position, \count($fields))), 0, [$field]);

        return $data->with(fields: $fields);
    }

    /**
     * The form without a field. Entries keep its value.
     *
     * @throws InvalidInputException
     */
    public function removeField(string $action, FormData $data, string $key): FormData
    {
        $this->fieldOf($action, $data, $key);

        return $data->with(fields: array_values(array_filter($data->fields, static fn(Field $each): bool => $each->key !== $key)));
    }

    /**
     * @throws InvalidInputException
     */
    public function fieldOf(string $action, FormData $data, string $key): Field
    {
        return $data->field($key) ?? throw new InvalidInputException($action, ['/key' => new Message('The form has no field "{key}".', ['key' => $key])]);
    }

    /**
     * Publishes the draft once it is complete: every language has a
     * title, the form has fields, each with a label in every language and
     * choices with options.
     *
     * @throws InvalidInputException
     * @throws EntityNotFoundException
     */
    public function publish(string $action, string $id): FormDetails
    {
        $draft = $this->store->draft($id);

        if (null === $draft) {
            $this->store->details($id);

            throw new InvalidInputException($action, ['/id' => 'The form has no draft to publish.']);
        }

        $errors = [];

        if ([] === $draft->translations) {
            $errors['/translations'] = new Message('needs at least one language with a title.');
        }

        foreach ($draft->translations as $language => $text) {
            if ('' === trim($text->title)) {
                $errors['/translations/' . $language . '/title'] = new Message('must not be empty.');
            }
        }

        if ([] === $draft->fields) {
            $errors['/fields'] = new Message('A form needs at least one field.');
        }

        foreach ($draft->fields as $index => $field) {
            foreach (array_keys($draft->translations) as $language) {
                $text = $field->translations[$language] ?? null;
                $at = '/fields/' . $index . '/translations/' . $language;

                if (null === $text || '' === trim($text->label)) {
                    $errors[$at . '/label'] = new Message('The field "{key}" needs a label in {language}.', ['key' => $field->key, 'language' => $language]);
                } elseif ($field->type->hasOptions() && [] === $text->options) {
                    $errors[$at . '/options'] = new Message('The field "{key}" needs options in {language}.', ['key' => $field->key, 'language' => $language]);
                }
            }
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        $this->revisions->publish(FormStore::TYPE, $id);

        return $this->store->details($id);
    }

    /**
     * Removes the draft. A form that was never published is gone then.
     *
     * @throws InvalidInputException
     * @throws EntityNotFoundException
     */
    public function discard(string $action, string $id): ?FormDetails
    {
        $this->store->details($id);

        if (null === $this->store->draft($id)) {
            throw new InvalidInputException($action, ['/id' => 'The form has no draft.']);
        }

        $this->revisions->discard(FormStore::TYPE, $id);

        return null === $this->store->published($id) ? null : $this->store->details($id);
    }

    /**
     * Deletes the form with its draft and its entries. The versions stay.
     *
     * @throws EntityNotFoundException
     */
    public function delete(string $id): void
    {
        $this->store->details($id);

        foreach ($this->entries->fetch(['where' => ['formId' => $id]]) as $entry) {
            $this->entries->delete($entry->id);
        }

        $this->revisions->discard(FormStore::TYPE, $id);
        $this->revisions->archive(FormStore::TYPE, $id);
    }

    /**
     * Turns the input into texts by language, each an object or null.
     *
     * @param array<mixed> $input
     *
     * @return array<string, array<mixed>|null>
     *
     * @throws InvalidInputException
     */
    private function byLanguage(string $action, array $input, string $at): array
    {
        if ([] !== $input && array_is_list($input)) {
            throw new InvalidInputException($action, [$at => 'must be an object by language code, e.g. {"de": {…}}.']);
        }

        $errors = [];
        $texts = [];

        foreach ($input as $language => $value) {
            $language = (string) $language;

            if (!$this->languages->has($language)) {
                $errors[$at . '/' . $language] = new Message('The website has no language "{language}".', ['language' => $language]);
            } elseif (null !== $value && (!\is_array($value) || ([] !== $value && array_is_list($value)))) {
                $errors[$at . '/' . $language] = new Message('must be an object or null.');
            } else {
                $texts[$language] = $value;
            }
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $texts;
    }

    /**
     * A trimmed text of the input, else the one before.
     *
     * @param array<mixed>           $value
     * @param array<string, Message> $errors
     */
    private function text(array $value, string $key, string $before, int $length, string $at, array &$errors): string
    {
        if (!\array_key_exists($key, $value)) {
            return $before;
        }

        if (!\is_string($value[$key])) {
            $errors[$at . '/' . $key] = new Message('must be a text.');

            return $before;
        }

        $text = trim($value[$key]);

        if (mb_strlen($text) > $length) {
            $errors[$at . '/' . $key] = new Message('may have at most {count} characters.', ['count' => $length]);
        }

        return $text;
    }

    /**
     * @throws InvalidInputException
     */
    private function checkKey(string $action, FormData $data, string $key, string $at = '/key'): void
    {
        if (1 !== preg_match('/' . Field::KEY_PATTERN . '/', $key)) {
            throw new InvalidInputException($action, [$at => new Message('must start with a small letter and have only letters and digits, e.g. "email" or "phoneNumber".')]);
        }

        if (null !== $data->field($key)) {
            throw new InvalidInputException($action, [$at => new Message('The form has a field "{key}" already.', ['key' => $key])]);
        }
    }

    /**
     * A key from the first label, e.g. "emailAddress" for "E-Mail-Adresse".
     *
     * @param array<string, FieldText> $translations
     */
    private function keyFor(FormData $data, array $translations): string
    {
        $language = (string) array_key_first($translations);
        $slug = Slug::fromTitle(array_values($translations)[0]->label ?? '', $language);
        $parts = array_values(array_filter(explode('-', $slug), static fn(string $part): bool => 1 === preg_match('/^[a-z0-9]+$/', $part)));
        $key = lcfirst(implode('', array_map(ucfirst(...), $parts)));
        $key = substr(1 === preg_match('/^[a-z]/', $key) ? $key : 'field' . ucfirst($key), 0, 34);

        $unique = $key;

        for ($number = 2; null !== $data->field($unique); ++$number) {
            $unique = $key . $number;
        }

        return $unique;
    }
}

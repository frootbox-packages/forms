<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Language\Languages;
use Frootbox\Core\Revision\Revisions;
use Frootbox\Core\Revision\RevisionStatus;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Forms\Entry\Entries;

/**
 * Reads the drafts and published versions of forms from revisions
 * (decision 0222). There is no table of forms.
 */
final readonly class FormStore
{
    public const string TYPE = 'frootbox/forms/form';

    public function __construct(
        private Revisions $revisions,
        private Entries $entries,
        private Languages $languages,
    ) {}

    public function draft(string $id): ?FormData
    {
        $revision = $this->revisions->draft(self::TYPE, $id);

        return null === $revision ? null : $this->load($revision->data);
    }

    public function published(string $id): ?FormData
    {
        $revision = $this->revisions->published(self::TYPE, $id);

        return null === $revision ? null : $this->load($revision->data);
    }

    /**
     * The texts of the form and its fields in the order of the languages,
     * the same on every database.
     *
     * @param array<mixed> $data
     */
    private function load(array $data): FormData
    {
        if (\is_array($data['translations'] ?? null)) {
            $data['translations'] = $this->languages->sort($data['translations']);
        }

        if (\is_array($data['fields'] ?? null)) {
            $data['fields'] = array_map(fn(mixed $field): mixed => \is_array($field) && \is_array($field['translations'] ?? null) ? ['translations' => $this->languages->sort($field['translations'])] + $field : $field, $data['fields']);
        }

        return FormData::fromArray($data);
    }

    /**
     * The draft, else the published version.
     *
     * @throws EntityNotFoundException
     */
    public function current(string $id): FormData
    {
        return $this->draft($id) ?? $this->published($id) ?? throw EntityNotFoundException::for(self::TYPE, $id);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function details(string $id): FormDetails
    {
        $draft = $this->revisions->draft(self::TYPE, $id);
        $published = $this->revisions->published(self::TYPE, $id);

        if (null === $draft && null === $published) {
            throw EntityNotFoundException::for(self::TYPE, $id);
        }

        return new FormDetails(
            $id,
            match (true) {
                null === $published => FormStatus::Draft,
                null === $draft => FormStatus::Published,
                default => FormStatus::Changed,
            },
            $published?->version,
            null === $draft ? null : $this->load($draft->data),
            null === $published ? null : $this->load($published->data),
            $this->entries->count(['formId' => $id, 'spam' => null]),
        );
    }

    /**
     * The ids of all forms with a draft or a published version.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = [];

        foreach ($this->revisions->fetch(['where' => ['type' => self::TYPE, 'status' => [RevisionStatus::Draft->value, RevisionStatus::Published->value]], 'order' => 'id']) as $revision) {
            $ids[$revision->entityId] = true;
        }

        return array_keys($ids);
    }
}

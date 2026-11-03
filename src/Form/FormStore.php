<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

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
    ) {}

    public function draft(string $id): ?FormData
    {
        $revision = $this->revisions->draft(self::TYPE, $id);

        return null === $revision ? null : FormData::fromArray($revision->data);
    }

    public function published(string $id): ?FormData
    {
        $revision = $this->revisions->published(self::TYPE, $id);

        return null === $revision ? null : FormData::fromArray($revision->data);
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
            null === $draft ? null : FormData::fromArray($draft->data),
            null === $published ? null : FormData::fromArray($published->data),
            $this->entries->count(['formId' => $id]),
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

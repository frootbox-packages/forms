<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms;

use Frootbox\Core\Revision\Revisions;
use Frootbox\Core\Search\AsSearchSource;
use Frootbox\Core\Search\SearchEntry;
use Frootbox\Core\Search\SearchSource;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Forms\Form\Field;
use Frootbox\Forms\Form\FormStore;

/**
 * Forms in the search of the backend, as editors see them: drafts first
 * (decision 0222). Entries are not searched, they hold personal data.
 */
#[AsSearchSource('frootbox/forms/form', 'Forms')]
final readonly class FormSource implements SearchSource
{
    public function __construct(private FormStore $store) {}

    public function changed(string $table, array $row): array
    {
        $id = Revisions::TABLE === $table && FormStore::TYPE === ($row['type'] ?? null) ? $row['entity_id'] ?? null : null;

        return \is_string($id) ? [$id] : [];
    }

    public function items(): iterable
    {
        yield from $this->store->ids();
    }

    public function entries(array $items): iterable
    {
        foreach ($items as $id) {
            try {
                $data = $this->store->details($id)->current();
            } catch (EntityNotFoundException) {
                continue;
            }

            foreach ($data->translations as $language => $text) {
                yield new SearchEntry(
                    item: $id,
                    title: '' === $text->title ? $id : $text->title,
                    text: implode("\n", array_map(static fn(Field $field): string => $field->label($language), $data->fields)),
                    permission: 'frootbox/forms/form.show',
                    language: $language,
                );
            }
        }
    }
}

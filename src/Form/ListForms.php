<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Language\Languages;

/**
 * @implements Query<list<FormSummary>>
 */
#[AsQuery('frootbox/forms/form.list', 'Lists the forms by title, with drafts and published ones')]
final readonly class ListForms implements Query
{
    public function __construct(
        #[Description('Language of the titles; left out for the first language of the website')]
        public ?string $language = null,
    ) {}

    /**
     * @return list<FormSummary>
     */
    public function handle(FormStore $store, Languages $languages): array
    {
        $language = null !== $this->language && $languages->has($this->language) ? $this->language : $languages->default()->code;
        $items = [];

        foreach ($store->ids() as $id) {
            $details = $store->details($id);
            $data = $details->current();
            $items[] = new FormSummary($id, $details->status, $data->text($language)->title, \count($data->fields), $details->entries, array_keys($data->translations));
        }

        usort($items, static fn(FormSummary $a, FormSummary $b): int => [mb_strtolower($a->title), $a->id] <=> [mb_strtolower($b->title), $b->id]);

        return $items;
    }
}

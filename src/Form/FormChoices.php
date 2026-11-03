<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Language\Languages;

/**
 * @implements Query<list<array{value: string, label: string}>>
 */
#[AsQuery('frootbox/forms/form.choices', 'The published forms as choices for the block "Form"', permission: 'frootbox/forms/form.list')]
final readonly class FormChoices implements Query
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function handle(FormStore $store, Languages $languages): array
    {
        $language = $languages->default()->code;
        $choices = [];

        foreach ($store->ids() as $id) {
            $published = $store->published($id);

            if (null !== $published) {
                $title = $published->text($language)->title;
                $choices[] = ['value' => $id, 'label' => '' === $title ? $id : $title];
            }
        }

        usort($choices, static fn(array $a, array $b): int => mb_strtolower($a['label']) <=> mb_strtolower($b['label']));

        return $choices;
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Forms\Form\FormStore;

/**
 * Takes entries out of the spam folder (decision 0261): they move to the
 * inbox and are mailed now, as if they had come through. Not undoable:
 * the mails leave the house.
 *
 * @implements Action<int>
 */
#[AsAction('frootbox/forms/entry.release', 'Moves entries of forms from the spam folder to the inbox and mails them to the recipients; returns how many', undoable: false)]
final readonly class ReleaseEntries implements Action
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[ListOf('string')]
        public array $ids,
    ) {}

    public function handle(Entries $entries, FormStore $store, EntryMails $mails): int
    {
        $released = 0;

        foreach (array_unique($this->ids) as $id) {
            $entry = $entries->fetchById($id);

            if (null === $entry || null === $entry->spam) {
                continue;
            }

            $entry = $entries->update($id, ['spam' => null]);
            $form = $store->published($entry->formId);

            if (null !== $form) {
                $mails->send($form, $entry);
            }

            ++$released;
        }

        return $released;
    }
}

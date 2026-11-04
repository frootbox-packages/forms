<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * A form for editors: its draft and its published version.
 */
final readonly class FormDetails
{
    public function __construct(
        public string $id,
        public FormStatus $status,
        public ?int $version,
        public ?FormData $draft,
        public ?FormData $published,
        /** Entries kept for this form in the inbox, without spam (decision 0261) */
        public int $entries = 0,
    ) {}

    /**
     * The draft, else the published version.
     */
    public function current(): FormData
    {
        return $this->draft ?? $this->published ?? new FormData();
    }
}

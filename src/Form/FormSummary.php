<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * A form in the list.
 */
final readonly class FormSummary
{
    /**
     * @param list<string> $languages
     */
    public function __construct(
        public string $id,
        public FormStatus $status,
        public string $title,
        public int $fields,
        public int $entries,
        public array $languages,
    ) {}
}

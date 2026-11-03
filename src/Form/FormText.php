<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * The texts of a form in one language.
 */
final readonly class FormText
{
    public function __construct(
        public string $title,
        /** The text of the button, empty for "Send" */
        public string $button = '',
        /** What visitors read after sending, empty for a general thanks */
        public string $success = '',
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['title'] ?? null) ? $data['title'] : '',
            \is_string($data['button'] ?? null) ? $data['button'] : '',
            \is_string($data['success'] ?? null) ? $data['success'] : '',
        );
    }

    /**
     * @return array{title: string, button: string, success: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'button' => $this->button, 'success' => $this->success];
    }
}

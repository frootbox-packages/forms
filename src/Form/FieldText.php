<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Form;

/**
 * The texts of a field in one language: what it is called, a hint below
 * it and, for choices, the options.
 */
final readonly class FieldText
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $label,
        public string $hint = '',
        public array $options = [],
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $options = \is_array($data['options'] ?? null) ? array_values(array_filter($data['options'], \is_string(...))) : [];

        return new self(
            \is_string($data['label'] ?? null) ? $data['label'] : '',
            \is_string($data['hint'] ?? null) ? $data['hint'] : '',
            $options,
        );
    }

    /**
     * @return array{label: string, hint: string, options: list<string>}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'hint' => $this->hint, 'options' => $this->options];
    }
}

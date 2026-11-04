<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Spam;

use Frootbox\Core\Extension\Registry;
use Psr\Container\ContainerInterface;

/**
 * Runs the rules for the content of all extensions (decision 0261).
 */
final readonly class SpamRules
{
    public function __construct(
        private Registry $registry,
        private ContainerInterface $container,
    ) {}

    /**
     * The reason of the first rule that finds spam, null if none does.
     */
    public function check(SpamSubject $subject): ?string
    {
        foreach ($this->registry->items(SpamChecks::class) as $item) {
            $check = $this->container->get($item->check);

            if (!$check instanceof SpamCheck) {
                throw new \LogicException(\sprintf('%s is no %s.', $item->check, SpamCheck::class));
            }

            $reason = $check->check($subject);

            if (null !== $reason) {
                return $reason;
            }
        }

        return null;
    }
}

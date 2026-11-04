<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\Extension\ExtensionException;
use Frootbox\Core\Extension\Registry;
use Frootbox\Forms\Form\FormData;
use Frootbox\Forms\FormsSettings;
use Psr\Container\ContainerInterface;

/**
 * The captchas of all extensions by name and the one a form uses
 * (decision 0261).
 */
final class Captchas
{
    /** No captcha: only the honeypot, the time and the throttle (decision 0222). */
    public const string NONE = 'none';

    /** The website decides, by the setting "captcha". */
    public const string DEFAULT = 'default';

    /** @var array<string, Captcha>|null */
    private ?array $captchas = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly ContainerInterface $container,
        private readonly FormsSettings $settings,
    ) {}

    /**
     * @return array<string, Captcha> by name, in the order of registration
     */
    public function all(): array
    {
        if (null !== $this->captchas) {
            return $this->captchas;
        }

        $captchas = [];

        foreach ($this->registry->items(Captcha::class) as $captcha) {
            if (isset($captchas[$captcha->name]) || self::NONE === $captcha->name || self::DEFAULT === $captcha->name) {
                throw new ExtensionException(\sprintf('The captcha "%s" is registered twice or has a reserved name. A name belongs to one extension only.', $captcha->name));
            }

            $captchas[$captcha->name] = $captcha;
        }

        return $this->captchas = $captchas;
    }

    public function get(string $name): ?Captcha
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * The captcha of a form, null for none. Its own choice, else the
     * setting; an unknown name falls back to the proof of work, never to
     * no protection.
     */
    public function of(FormData $form): ?Captcha
    {
        $name = $form->captcha ?? $this->standard();

        if (self::NONE === $name) {
            return null;
        }

        return $this->get($name) ?? $this->get(ProofOfWork::NAME);
    }

    /**
     * The name the setting gives: a captcha or "none".
     */
    public function standard(): string
    {
        $name = trim($this->settings->captcha);

        return '' === $name ? ProofOfWork::NAME : $name;
    }

    public function provider(Captcha $captcha): CaptchaProvider
    {
        $provider = $this->container->get($captcha->provider);

        return $provider instanceof CaptchaProvider ? $provider : throw new \LogicException(\sprintf('%s is no %s.', $captcha->provider, CaptchaProvider::class));
    }
}

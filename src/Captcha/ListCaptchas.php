<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;

/**
 * The captchas a form can choose and the standard of the website
 * (decision 0261).
 *
 * @implements Query<CaptchaList>
 */
#[AsQuery('frootbox/forms/captcha.list', 'Lists the captchas a form can choose, with the standard of the website', permission: 'frootbox/forms/form.show')]
final readonly class ListCaptchas implements Query
{
    public function handle(Captchas $captchas): CaptchaList
    {
        $standard = $captchas->standard();

        return new CaptchaList(
            array_values(array_map(static fn(Captcha $captcha): array => [
                'name' => $captcha->name,
                'title' => $captcha->title,
                'description' => $captcha->description,
                'service' => $captcha->service,
                'ready' => $captchas->ready($captcha),
            ], $captchas->all())),
            Captchas::NONE === $standard || null !== $captchas->get($standard) ? $standard : ProofOfWork::NAME,
        );
    }
}

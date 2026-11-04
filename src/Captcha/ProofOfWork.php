<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\Routing\Router;
use Frootbox\Core\Security\Secrets;

/**
 * The standard captcha, invisible for people (decision 0261). The browser
 * fetches a fresh task and searches the number up to MAX whose SHA-256
 * with the salt gives the hash of the task, as ALTCHA does. That costs a
 * moment in a browser and a script without one cannot send at all. A
 * task is signed, valid an hour and counts once. A missing or wrong
 * solution is suspicious, not wrong: whoever has no JavaScript is not
 * lost, the entry goes to the spam folder.
 */
final readonly class ProofOfWork implements CaptchaProvider
{
    public const string NAME = 'frootbox/forms/proof-of-work';

    /** The highest number of a task; half of it is tried on average. */
    public const int MAX = 200_000;

    /** Seconds a task is valid. */
    public const int VALID = 3600;

    private const string PURPOSE = 'frootbox/forms/proof';

    public function __construct(
        private Secrets $secrets,
        private Router $router,
        private Proofs $proofs,
    ) {}

    public function widget(CaptchaRequest $request): CaptchaWidget
    {
        $url = $this->router->path('frootbox.forms.proof', ['id' => $request->formId]);

        return new CaptchaWidget(
            '<div class="forms__proof" data-forms-proof="' . htmlspecialchars($url) . '" hidden>'
            . '<input type="hidden" name="captcha[task]"><input type="hidden" name="captcha[number]"></div>',
        );
    }

    /**
     * A fresh task: the salt, the hash to find and the task to send back.
     *
     * @return array{task: string, salt: string, challenge: string, max: int}
     */
    public function task(string $formId, ?int $now = null, ?int $number = null): array
    {
        $salt = (($now ?? time()) + self::VALID) . '.' . bin2hex(random_bytes(12));
        $challenge = hash('sha256', $salt . ($number ?? random_int(0, self::MAX)));
        $signature = $this->secrets->sign(self::PURPOSE, $formId . '|' . $salt . '|' . $challenge) ?? 'unsigned-without-app-secret';

        return ['task' => $salt . '.' . $challenge . '.' . $signature, 'salt' => $salt, 'challenge' => $challenge, 'max' => self::MAX];
    }

    public function verify(CaptchaRequest $request, array $answer, ?int $now = null): CaptchaResult
    {
        if (!$this->secrets->available()) {
            return CaptchaResult::passed();
        }

        $task = $answer['task'] ?? '';
        $number = $answer['number'] ?? '';

        if (1 !== preg_match('/^((\d{1,12})\.[0-9a-f]{24})\.([0-9a-f]{64})\.([A-Za-z0-9_-]{20,100})$/', $task, $match) || 1 !== preg_match('/^\d{1,7}$/', $number) || (int) $number > self::MAX) {
            return CaptchaResult::suspicious('The proof of work is missing.');
        }

        [, $salt, $expires, $challenge, $signature] = $match;

        if (!$this->secrets->verify(self::PURPOSE, $request->formId . '|' . $salt . '|' . $challenge, $signature) || !hash_equals($challenge, hash('sha256', $salt . $number))) {
            return CaptchaResult::suspicious('The proof of work is wrong.');
        }

        if ((int) $expires < ($now ?? time())) {
            return CaptchaResult::suspicious('The proof of work is out of date.');
        }

        return $this->proofs->use($task) ? CaptchaResult::passed() : CaptchaResult::suspicious('The proof of work was used before.');
    }
}

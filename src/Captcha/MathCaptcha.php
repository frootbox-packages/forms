<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Security\Secrets;
use Frootbox\Core\Support\Message;

/**
 * A sum to solve, usable without JavaScript (decision 0261). The result
 * is signed with the form, so the server keeps nothing. Pages come from
 * the cache, so visitors of a page see the same sum for a while; it does
 * not count once. A wrong result is an error at the field.
 */
final readonly class MathCaptcha implements CaptchaProvider
{
    public const string NAME = 'frootbox/forms/math';

    private const string PURPOSE = 'frootbox/forms/math';

    public function __construct(
        private Secrets $secrets,
        private Translator $translator,
    ) {}

    public function widget(CaptchaRequest $request, ?int $first = null, ?int $second = null): CaptchaWidget
    {
        $first ??= random_int(1, 9);
        $second ??= random_int(1, 9);
        $nonce = bin2hex(random_bytes(8));
        $task = $nonce . '.' . ($this->secrets->sign(self::PURPOSE, $this->text($request->formId, $nonce, (string) ($first + $second))) ?? 'unsigned-without-app-secret');
        $id = 'form-' . $request->formId . '-captcha';
        $question = $this->translator->in($request->language, 'What is {first} plus {second}?', ['frootbox/forms'], ['first' => $first, 'second' => $second]);

        return new CaptchaWidget(
            '<div class="forms__field forms__field--captcha" data-forms-field="captcha">'
            . '<label class="forms__label" for="' . $id . '">' . htmlspecialchars($question) . '<span class="forms__required" aria-hidden="true">*</span></label>'
            . '<p class="forms__hint" id="' . $id . '-hint">' . htmlspecialchars($this->translator->in($request->language, 'This keeps the form free of spam.', ['frootbox/forms'])) . '</p>'
            . '<input class="forms__input forms__input--short" type="text" inputmode="numeric" id="' . $id . '" name="captcha[answer]" maxlength="3" autocomplete="off" required aria-describedby="' . $id . '-hint ' . $id . '-error">'
            . '<input type="hidden" name="captcha[task]" value="' . htmlspecialchars($task) . '">'
            . '<p class="forms__error" id="' . $id . '-error" data-forms-error hidden></p></div>',
        );
    }

    public function verify(CaptchaRequest $request, array $answer): CaptchaResult
    {
        $given = trim($answer['answer'] ?? '');

        if ('' === $given) {
            return CaptchaResult::wrong(new Message('Please solve the sum.'));
        }

        if (!$this->secrets->available()) {
            return CaptchaResult::passed();
        }

        if (1 !== preg_match('/^([0-9a-f]{16})\.([A-Za-z0-9_-]{20,100})$/', $answer['task'] ?? '', $match)) {
            return CaptchaResult::suspicious('The task of the sum is missing.');
        }

        return 1 === preg_match('/^\d{1,3}$/', $given) && $this->secrets->verify(self::PURPOSE, $this->text($request->formId, $match[1], (string) (int) $given), $match[2])
            ? CaptchaResult::passed()
            : CaptchaResult::wrong(new Message('The result is not right. Please try again.'));
    }

    private function text(string $formId, string $nonce, string $result): string
    {
        return $formId . '|' . $nonce . '|' . $result;
    }
}

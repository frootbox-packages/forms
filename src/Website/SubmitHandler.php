<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Api\JsonResponder;
use Frootbox\Core\Auth\TooManyAttemptsException;
use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Support\Message;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Receives forms of the website at POST /_forms/submit (decision 0222)
 * and passes them to frootbox/forms/form.submit. With JavaScript it
 * answers JSON, errors by field in the language of the form. Without, it
 * leads back to the page: ?fbForm=<id> for the thanks, ?fbFormError=<id>
 * for an error.
 */
final readonly class SubmitHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_forms/submit';

    public function __construct(
        private Runner $runner,
        private JsonResponder $json,
        private ResponseFactoryInterface $responses,
        private Translator $translator,
        private Languages $languages,
        private FormFiles $files,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = \is_array($body) ? $body : [];
        $text = static fn(string $name): ?string => \is_string($body[$name] ?? null) && '' !== $body[$name] ? $body[$name] : null;

        $id = $text('_form') ?? '';
        $language = $text('_language');
        $language = null !== $language && $this->languages->has($language) ? $language : $this->languages->default()->code;
        $wantsJson = str_contains($request->getHeaderLine('Accept'), 'application/json');
        $back = self::back($text('_return'));

        $input = array_filter([
            'id' => $id,
            'values' => [...(\is_array($body['values'] ?? null) ? $body['values'] : []), ...$this->uploads($request)],
            'language' => $language,
            'token' => $text('_token'),
            'website' => $text('website'),
            'page' => $text('_page'),
            'about' => $text('_about'),
            // What the captcha gave, in fields "captcha[…]" (decision 0261).
            'captcha' => \is_array($body['captcha'] ?? null) ? $body['captcha'] : null,
        ], static fn(mixed $value): bool => null !== $value);

        try {
            $result = $this->runner->call('frootbox/forms/form.submit', $input);
        } catch (InvalidInputException $error) {
            return $wantsJson
                ? $this->json->respond([
                    'title' => $this->translate($language, new Message('The form could not be sent. Please check your entries and try again.')),
                    'status' => 422,
                    'errors' => array_map(fn(Message $message): string => $this->translate($language, $message), $error->messages),
                ], 422)
                : $this->redirect($back, 'fbFormError', $id);
        } catch (TooManyAttemptsException $error) {
            return $wantsJson
                ? $this->json->respond(['title' => $this->translate($language, $error->template), 'status' => 429], 429)->withHeader('Retry-After', (string) $error->retryAfter)
                : $this->redirect($back, 'fbFormError', $id);
        }

        if (!$wantsJson) {
            return $this->redirect($back, 'fbForm', $id);
        }

        $message = $result instanceof SubmitResult ? $result->message : '';

        return $this->json->respond(['data' => ['message' => '' === $message ? $this->translate($language, new Message('Thank you, your message has been sent.')) : $message]]);
    }

    /**
     * The files of the fields with files, "files[<key>][]", as tokens of
     * uploads (decision 0230).
     *
     * @return array<string, list<string>>
     */
    private function uploads(ServerRequestInterface $request): array
    {
        $fields = $request->getUploadedFiles()['files'] ?? null;
        $uploads = [];

        if (!\is_array($fields)) {
            return [];
        }

        foreach ($fields as $key => $files) {
            $files = \is_array($files) ? $files : [$files];
            $tokens = [];

            foreach (\array_slice($files, 0, FormFiles::MOST + 1) as $file) {
                if ($file instanceof UploadedFileInterface && \UPLOAD_ERR_NO_FILE !== $file->getError()) {
                    $tokens[] = $this->files->receive($file);
                }
            }

            $uploads[(string) $key] = $tokens;
        }

        return $uploads;
    }

    private function translate(string $language, Message $message): string
    {
        return $this->translator->in($language, $message->text, ['frootbox/forms'], $message->parameters);
    }

    /**
     * Back to the page with the form and the result in the query.
     */
    private function redirect(string $back, string $parameter, string $id): ResponseInterface
    {
        $path = (string) parse_url($back, \PHP_URL_PATH);
        parse_str((string) parse_url($back, \PHP_URL_QUERY), $query);
        unset($query['fbForm'], $query['fbFormError']);
        $query[$parameter] = $id;

        return $this->responses->createResponse(303)
            ->withHeader('Location', $path . '?' . http_build_query($query) . '#form-' . $id)
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * The path to lead back to: one on this website, else the start.
     */
    private static function back(?string $return): string
    {
        return null !== $return && 1 === preg_match('~^/(?![/\\\\])~', $return) && 0 === preg_match('~[\x00-\x1f]~', $return) ? $return : '/';
    }
}

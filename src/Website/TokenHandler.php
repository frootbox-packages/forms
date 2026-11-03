<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Api\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A fresh token for a form at GET /_forms/token/<id> (decision 0222).
 * Pages come from the cache, so the script of the form asks for the time
 * it was really shown.
 */
final readonly class TokenHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_forms/token/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}';

    public function __construct(
        private FormToken $tokens,
        private JsonResponder $json,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        return $this->json->respond(['data' => ['token' => $this->tokens->issue(\is_string($id) ? $id : '')]])
            ->withHeader('Cache-Control', 'no-store');
    }
}

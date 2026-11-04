<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Captcha;

use Frootbox\Core\Api\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A fresh task of the proof of work at GET /_forms/proof/<id> (decision
 * 0261), never from a cache.
 */
final readonly class ProofHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_forms/proof/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}';

    public function __construct(
        private ProofOfWork $proofs,
        private JsonResponder $json,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        return $this->json->respond(['data' => $this->proofs->task(\is_string($id) ? $id : '')])
            ->withHeader('Cache-Control', 'no-store');
    }
}

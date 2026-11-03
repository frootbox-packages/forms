<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Forms\Entry\ShowEntry;
use Frootbox\Forms\Website\FormFiles;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A file a visitor sent with a form (decision 0230), as a download for
 * users who may see the entries: frootbox/forms/entry.show checks the
 * permission, and only files the entry names are served.
 */
final readonly class FileHandler implements RequestHandlerInterface
{
    public function __construct(
        private BackendView $view,
        private Runner $runner,
        private FormFiles $files,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $entryId = $request->getAttribute('entry');
        $fileId = $request->getAttribute('file');

        if (!\is_string($entryId) || !\is_string($fileId)) {
            throw HttpException::notFound($request->getUri()->getPath());
        }

        $entry = $this->runner->ask(new ShowEntry($entryId));
        $name = null;

        foreach ($entry->fields as $field) {
            foreach ($field['files'] ?? [] as $file) {
                $name = $file['id'] === $fileId ? $file['name'] : $name;
            }
        }

        $path = null === $name ? null : $this->files->path($entry->id, $fileId);

        if (null === $path) {
            throw HttpException::notFound($request->getUri()->getPath());
        }

        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);

        return $this->responses->createResponse()
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name))
            ->withHeader('Content-Length', (string) filesize($path))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withBody($this->streams->createStreamFromFile($path));
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Forms\Form\ListForms;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "Forms": all forms by title (decision 0222).
 */
final readonly class FormsHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/forms/form.list';

    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        return $this->view->render($request, '@frootbox/forms/backend/forms.twig', [
            'forms' => $this->runner->ask(new ListForms()),
        ]);
    }
}

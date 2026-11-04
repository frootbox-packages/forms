<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Backend;

use Frootbox\Backend\Http\Pages\PageTree;
use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\ShowPageTree;
use Frootbox\Forms\Captcha\ListCaptchas;
use Frootbox\Forms\Form\Field;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\ShowForm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A new form or one to edit (decision 0222): texts by language and the
 * recipients in the draft, the fields each through their own action,
 * publishing and deleting.
 */
final readonly class FormHandler implements RequestHandlerInterface
{
    public function __construct(
        private BackendView $view,
        private Runner $runner,
        private Languages $languages,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $id = $request->getAttribute('id');

        if (!\is_string($id)) {
            return $this->view->render($request, '@frootbox/forms/backend/form-new.twig', [
                'language' => $this->languages->default(),
            ]);
        }

        $details = $this->runner->ask(new ShowForm($id));
        $chosen = $request->getQueryParams()['language'] ?? null;
        $language = \is_string($chosen) && $this->languages->has($chosen) ? $this->languages->get($chosen) : $this->languages->default();
        $data = $details->current();
        $code = $language->code;

        // The pages for the link of a consent field (decision 0259).
        try {
            $pages = array_map(static fn(array $option): array => ['value' => 'page:' . $option['value'], 'label' => $option['label']], PageTree::of($this->runner->ask(new ShowPageTree()), $this->languages)->options());
        } catch (AccessDeniedException) {
            $pages = [];
        }

        return $this->view->render($request, '@frootbox/forms/backend/form.twig', [
            'form' => $details,
            'data' => $data,
            'title' => '' !== ($data->translations[$code]->title ?? '') ? $data->translations[$code]->title : $data->text($this->languages->default()->code)->title,
            'language' => $language,
            'languages' => $this->languages->all(),
            'translation' => $data->translations[$code] ?? null,
            'source' => $data->text($this->languages->default()->code),
            'pages' => $pages,
            // The choice of the tab "Spam protection" (decision 0261).
            'captchas' => $this->runner->ask(new ListCaptchas()),
            'types' => array_map(static fn(FieldType $type): array => ['value' => $type->value, 'label' => $type->title()], FieldType::cases()),
            'fields' => array_map(static fn(Field $field): array => [
                'key' => $field->key,
                'type' => $field->type,
                'required' => $field->isRequired(),
                'label' => $field->translations[$code]->label ?? '',
                'fill' => [
                    'key' => $field->key,
                    'type' => $field->type->value,
                    'required' => $field->required,
                    'link' => $field->link ?? '',
                    'translations.' . $code . '.label' => $field->translations[$code]->label ?? '',
                    'translations.' . $code . '.hint' => $field->translations[$code]->hint ?? '',
                    'translations.' . $code . '.options' => implode("\n", $field->translations[$code]->options ?? []),
                ],
            ], $data->fields),
        ]);
    }
}

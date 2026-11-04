<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Blocks;

use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Routing\Router;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Forms\Captcha\CaptchaRequest;
use Frootbox\Forms\Captcha\Captchas;
use Frootbox\Forms\Form\Field;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\FormStore;
use Frootbox\Forms\Website\FormFiles;
use Frootbox\Forms\Website\FormToken;

/**
 * Shows a published form (decision 0222). After sending without
 * JavaScript the page comes back with ?fbForm=<id> for the thanks or
 * ?fbFormError=<id> for an error; both are part of the cache key.
 */
final readonly class Form implements BlockController
{
    public function __construct(
        private FormStore $store,
        private FormToken $tokens,
        private Router $router,
        private Urls $urls,
        private Languages $languages,
        private Captchas $captchas,
    ) {}

    public function data(BlockContext $context): array
    {
        $id = \is_string($context->options['form'] ?? null) ? $context->options['form'] : '';
        $form = '' === $id ? null : $this->store->published($id);

        if (null === $form) {
            return ['form' => null];
        }

        $language = $form->language($context->contentLanguage) ?? $context->contentLanguage;
        $text = $form->text($language);
        $page = null !== $context->target && $context->target->isPage() ? $context->target->id : null;
        $captcha = $this->captchas->of($form);
        $widget = null === $captcha ? null : $this->captchas->provider($captcha)->widget(new CaptchaRequest($id, $language));

        return [
            'form' => [
                'id' => $id,
                'title' => $text->title,
                'button' => $text->button,
                'success' => $text->success,
                'language' => $language,
                'direction' => $this->languages->has($language) ? $this->languages->get($language)->direction->value : 'ltr',
                'action' => $this->router->path('frootbox.forms.submit'),
                'token' => $this->tokens->issue($id),
                'tokenUrl' => $this->router->path('frootbox.forms.token', ['id' => $id]),
                'page' => $page,
                // On the address of an object, e.g. a job, the entry is about it (decision 0230).
                'about' => null === $context->subpage ? null : (string) $context->subpage->target,
                'return' => null === $context->target ? '' : $this->urls->href($context->target, $context->language) ?? '',
                'required' => [] !== array_filter($form->fields, static fn(Field $field): bool => $field->isRequired()),
                'files' => [] !== array_filter($form->fields, static fn(Field $field): bool => FieldType::Files === $field->type),
                'accept' => FormFiles::ACCEPT,
                'most' => FormFiles::MOST,
                'maxMb' => FormFiles::MAX_MB,
                // The captcha of the form; one of another provider loads after consent (decision 0261).
                'captcha' => null === $widget ? null : ['html' => $widget->html, 'service' => $widget->service ?? $captcha->service, 'title' => $widget->title],
                'fields' => array_map(fn(Field $field): array => [
                    'key' => $field->key,
                    'type' => $field->type->value,
                    'choice' => $field->type->hasOptions(),
                    'required' => $field->isRequired(),
                    'label' => $field->label($language),
                    // For consent, a link to the privacy policy (decision 0259); none if the page is not published.
                    'link' => null === $field->link ? null : $this->link($field->link, $context->language),
                    'linkParts' => $field->linkParts($language),
                    'hint' => $field->text($language)->hint,
                    'options' => $field->text($language)->options,
                    'input' => match ($field->type) {
                        FieldType::Email => 'email',
                        FieldType::Phone => 'tel',
                        FieldType::Number => 'number',
                        FieldType::Date => 'date',
                        default => 'text',
                    },
                    'autocomplete' => match ($field->type) {
                        FieldType::Email => 'email',
                        FieldType::Phone => 'tel',
                        default => null,
                    },
                    'maxLength' => $field->type->maxLength(),
                ], $form->fields),
            ],
            'sent' => $id === $context->query('fbForm'),
            'failed' => $id === $context->query('fbFormError'),
        ];
    }

    private function link(string $link, string $language): ?string
    {
        $target = Target::tryParse($link);

        return null === $target ? $link : $this->urls->href($target, $language);
    }
}

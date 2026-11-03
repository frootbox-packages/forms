<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Language\Languages;
use Frootbox\Forms\Entry\Entry;
use Frootbox\Forms\Entry\ListEntries;
use Frootbox\Forms\Form\ShowForm;
use Frootbox\Forms\FormsSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The entries of a form, newest first, with the values of each and the
 * export (decision 0222).
 */
final readonly class EntriesHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/forms/entry.list';

    private const int PER_PAGE = 50;

    public function __construct(
        private BackendView $view,
        private Runner $runner,
        private Languages $languages,
        private FormsSettings $settings,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $id = $request->getAttribute('id');
        $id = \is_string($id) ? $id : '';
        $page = $request->getQueryParams()['page'] ?? null;
        $page = \is_string($page) && ctype_digit($page) ? max(1, (int) $page) : 1;
        $chosen = $request->getQueryParams()['language'] ?? null;
        $language = \is_string($chosen) && $this->languages->has($chosen) ? $this->languages->get($chosen) : $this->languages->default();
        $details = $this->runner->ask(new ShowForm($id));
        $list = $this->runner->ask(new ListEntries($id, $page, self::PER_PAGE));

        return $this->view->render($request, '@frootbox/forms/backend/entries.twig', [
            'form' => $details,
            'title' => $details->current()->text($this->languages->default()->code)->title,
            'list' => $list,
            // The language of the form screen, so the tabs back to it keep it.
            'language' => $language,
            'keepDays' => $this->settings->keepDays,
            'entries' => array_map(static fn(Entry $entry): array => [
                'id' => $entry->id,
                'createdAt' => $entry->createdAt,
                'language' => $entry->language,
                'summary' => self::summary($entry),
                'about' => $entry->aboutTitle,
                'fields' => $entry->fields,
            ], $list->entries),
        ]);
    }

    /**
     * The first values of an entry as one line, e.g. "Ada · ada@example.org".
     */
    private static function summary(Entry $entry): string
    {
        $parts = [];

        foreach ($entry->fields as $field) {
            if (\is_string($field['value']) && '' !== $field['value'] && !str_contains($field['value'], "\n")) {
                $parts[] = mb_strimwidth($field['value'], 0, 60, '…');
            }

            if (\count($parts) >= 2) {
                break;
            }
        }

        return implode(' · ', $parts);
    }
}

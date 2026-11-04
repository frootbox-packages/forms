<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\Slug;
use Frootbox\Forms\Entry\Entry;
use Frootbox\Forms\Entry\ListEntries;
use Frootbox\Forms\Form\ShowForm;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The entries of a form as CSV for a spreadsheet (decision 0222): one
 * row per entry, one column per field, oldest first. Only presents what
 * frootbox/forms/entry.list returns.
 */
final readonly class ExportHandler implements RequestHandlerInterface
{
    private const int PER_PAGE = 1000;

    public function __construct(
        private BackendView $view,
        private Runner $runner,
        private Languages $languages,
        private Translator $translator,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $id = $request->getAttribute('id');
        $id = \is_string($id) ? $id : '';
        $form = $this->runner->ask(new ShowForm($id))->current();
        $default = $this->languages->default()->code;
        $entries = [];

        for ($page = 1, $pages = 1; $page <= $pages; ++$page) {
            $list = $this->runner->ask(new ListEntries($id, $page, self::PER_PAGE));
            array_push($entries, ...$list->entries);
            $pages = $list->pages;
        }

        // The columns: the fields of the form, then those only old entries have.
        $columns = [];

        foreach ($form->fields as $field) {
            $columns[$field->key] = $field->label($default);
        }

        foreach ($entries as $entry) {
            foreach ($entry->fields as $field) {
                $columns[$field['key']] ??= $field['label'];
            }
        }

        $yes = $this->translator->get('yes');
        $no = $this->translator->get('no');
        $about = [] !== array_filter($entries, static fn(Entry $entry): bool => null !== $entry->aboutTitle);
        $rows = [[$this->translator->get('Sent'), $this->translator->get('Language'), ...($about ? [$this->translator->get('Regarding')] : []), ...array_values($columns)]];

        foreach (array_reverse($entries) as $entry) {
            $values = array_column($entry->fields, 'value', 'key');
            $row = [$entry->createdAt->format('Y-m-d H:i:s'), $entry->language, ...($about ? [$entry->aboutTitle ?? ''] : [])];

            foreach (array_keys($columns) as $key) {
                $row[] = isset($values[$key]) ? Entry::text($values[$key], $yes, $no) : '';
            }

            $rows[] = $row;
        }

        $name = Slug::fromTitle($form->text($default)->title, $default);

        return $this->responses->createResponse()
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', \sprintf('attachment; filename="%s.csv"', '' === $name || 1 !== preg_match('/^[a-z0-9-]+$/', $name) ? 'entries' : $name))
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream("\u{FEFF}" . self::csv($rows)));
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function csv(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(static function (string $cell): string {
                // A cell that starts like a formula is read as text (CSV injection).
                if (1 === preg_match('/^[=+\-@\t\r]/', $cell)) {
                    $cell = "'" . $cell;
                }

                return '"' . str_replace('"', '""', $cell) . '"';
            }, $row));
        }

        return implode("\r\n", $lines) . "\r\n";
    }
}

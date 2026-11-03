<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Forms\Backend\EntriesHandler;
use Frootbox\Forms\Backend\ExportHandler;
use Frootbox\Forms\Backend\FormHandler;
use Frootbox\Forms\Backend\FormsHandler;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\FormsExtension;
use Frootbox\Forms\Website\FormToken;
use Frootbox\Forms\Website\Sendings;
use Frootbox\Forms\Website\SubmitForm;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormsHandler::class)]
#[CoversClass(FormHandler::class)]
#[CoversClass(EntriesHandler::class)]
#[CoversClass(ExportHandler::class)]
#[CoversClass(FormsExtension::class)]
final class BackendTest extends BackendTestCase
{
    protected function tables(): array
    {
        return [Entries::TABLE, Sendings::TABLE];
    }

    protected function extensions(): Extensions
    {
        $forms = ExtensionPackage::fromManifest('frootbox/forms', (string) realpath(FormsTestCase::ROOT), psr4: ['Frootbox\Forms\\' => ['src/'], 'Frootbox\Forms\Migrations\\' => ['migrations/']]);

        return new Extensions([self::backend(), $forms], 'forms-backend');
    }

    protected function config(): array
    {
        return ['languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    public function testEditorsBuildAndPublishForms(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        $list = $this->html($this->request('GET', '/cms/forms', session: $session));
        self::assertStringContainsString('href="/cms/forms" aria-current="page"', $list, 'The sidebar has the screen.');
        self::assertStringContainsString('Noch keine Formulare', $list);
        self::assertStringContainsString('<fb-form action="frootbox/forms/form.create" next="/cms/forms/{id}"', $this->html($this->request('GET', '/cms/forms/new', session: $session)));

        $id = $this->id($this->action('frootbox/forms/form.create', ['translations' => ['de' => ['title' => 'Kontakt']], 'recipients' => ['team@example.org']], $session));
        $this->action('frootbox/forms/form.field-add', ['id' => $id, 'type' => 'email', 'translations' => ['de' => ['label' => 'E-Mail', 'hint' => '', 'options' => []]], 'required' => true], $session);
        $this->action('frootbox/forms/form.field-add', ['id' => $id, 'type' => 'select', 'translations' => ['de' => ['label' => 'Anliegen', 'hint' => '', 'options' => ['Frage', 'Lob']]], 'required' => false], $session);

        $show = $this->html($this->request('GET', '/cms/forms/' . $id, session: $session));
        self::assertStringContainsString('<h1 class="fb-heading-1" tabindex="-1" data-fb-focus>Kontakt</h1>', $show);
        self::assertStringContainsString('name="translations.de.button"', $show);
        self::assertStringContainsString('>Auswahlliste · <code>anliegen</code>', $show, 'The type in German and the key.');
        self::assertStringContainsString('&quot;translations.de.options&quot;:&quot;Frage\nLob&quot;', $show, 'The options fill the dialog one per line.');
        self::assertStringContainsString('<fb-form action="frootbox/forms/form.field-move"', $show);
        self::assertStringContainsString('team@example.org</textarea>', $show);
        self::assertStringContainsString('<fb-form action="frootbox/forms/form.publish"', $show);
        self::assertStringNotContainsString('href="/cms/forms/' . $id . '/entries"', $show, 'Editors do not see the entries.');

        $this->action('frootbox/forms/form.publish', ['id' => $id], $session);
        self::assertStringContainsString('href="/cms/forms/' . $id . '">Kontakt</a>', $this->html($this->request('GET', '/cms/forms', session: $session)));
        self::assertSame(403, $this->request('GET', '/cms/forms/' . $id . '/entries', session: $session)->getStatusCode());

        $english = $this->html($this->request('GET', '/cms/forms/' . $id . '?language=en', session: $session));
        self::assertStringContainsString('name="translations.en.title" value="Kontakt"', $english);
    }

    public function testAdminsReadAndExportTheEntries(): void
    {
        $this->createUser('admin@example.org', ['admin']);
        $session = $this->signIn('admin@example.org');
        $id = $this->id($this->action('frootbox/forms/form.create', ['translations' => ['de' => ['title' => 'Kontakt']]], $session));
        $this->action('frootbox/forms/form.field-add', ['id' => $id, 'type' => 'text', 'key' => 'name', 'translations' => ['de' => ['label' => 'Name']]], $session);
        $this->action('frootbox/forms/form.field-add', ['id' => $id, 'type' => 'textarea', 'key' => 'message', 'translations' => ['de' => ['label' => 'Nachricht']]], $session);
        $this->action('frootbox/forms/form.publish', ['id' => $id], $session);
        $token = $this->get(FormToken::class)->issue($id, time() - 60);
        $this->runner()->run(new SubmitForm($id, ['name' => '=1+1', 'message' => "Zwei\nZeilen"], 'de', $token), Context::guest(address: '192.0.2.5'));

        $entries = $this->html($this->request('GET', '/cms/forms/' . $id . '/entries', session: $session));
        self::assertStringContainsString('1 Einsendung', $entries);
        self::assertStringContainsString('Zwei<br />' . "\n" . 'Zeilen', $entries);
        self::assertStringContainsString('Einsendungen werden nach 180 Tagen gelöscht.', $entries);
        self::assertStringContainsString('<fb-form action="frootbox/forms/entry.delete"', $entries);

        $csv = $this->request('GET', '/cms/forms/' . $id . '/entries.csv', session: $session);
        self::assertSame('text/csv; charset=utf-8', $csv->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="kontakt.csv"', $csv->getHeaderLine('Content-Disposition'));
        $lines = explode("\r\n", (string) $csv->getBody());
        self::assertSame("\u{FEFF}\"Gesendet\",\"Sprache\",\"Name\",\"Nachricht\"", $lines[0]);
        self::assertStringEndsWith(",\"de\",\"'=1+1\",\"Zwei\nZeilen\"", $lines[1], 'A formula is read as text.');
    }

    /**
     * @param array<mixed> $answer
     */
    private function id(array $answer): string
    {
        self::assertIsArray($answer['data'] ?? null);
        $id = $answer['data']['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }
}

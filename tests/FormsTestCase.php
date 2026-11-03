<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Core\Action\Context;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Mail\Mailer;
use Frootbox\Core\Mail\MemoryTransport;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Form\AddField;
use Frootbox\Forms\Form\CreateForm;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Website\FormToken;
use Frootbox\Forms\Website\Sendings;
use Frootbox\Forms\Website\SubmitForm;
use Frootbox\Forms\Website\SubmitResult;
use Nyholm\Psr7\ServerRequest;

/**
 * A website of the core fixtures with the forms installed.
 */
abstract class FormsTestCase extends NotesTestCase
{
    public const string ROOT = __DIR__ . '/..';

    protected function tables(): array
    {
        return [Entries::TABLE, Sendings::TABLE];
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        $forms = ExtensionPackage::fromManifest('frootbox/forms', (string) realpath(self::ROOT), psr4: ['Frootbox\Forms\\' => ['src/'], 'Frootbox\Forms\Migrations\\' => ['migrations/']]);

        return new Extensions([...FixtureSite::extensions('base', 'site', 'plugin')->packages, $forms], 'forms');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    protected function env(): array
    {
        return ['MAILER_DSN' => 'memory://default'];
    }

    /**
     * A published contact form: name, email, message and consent.
     *
     * @param list<string> $recipients
     */
    protected function contactForm(array $recipients = ['team@example.org'], bool $publish = true): string
    {
        $id = $this->runner()->run(new CreateForm(['de' => ['title' => 'Kontakt', 'success' => 'Danke, wir melden uns.']], $recipients))->id;
        $this->runner()->run(new AddField($id, FieldType::Text, ['de' => ['label' => 'Name']], true));
        $this->runner()->run(new AddField($id, FieldType::Email, ['de' => ['label' => 'E-Mail-Adresse']], true));
        $this->runner()->run(new AddField($id, FieldType::Textarea, ['de' => ['label' => 'Nachricht']], key: 'message'));
        $this->runner()->run(new AddField($id, FieldType::Consent, ['de' => ['label' => 'Ich stimme der Datenschutzerklärung zu.']], key: 'privacy'));

        if ($publish) {
            $this->runner()->run(new PublishForm($id));
        }

        return $id;
    }

    /**
     * A token as if the form was shown a minute ago.
     */
    protected function token(string $id, int $ago = 60): string
    {
        return $this->get(FormToken::class)->issue($id, time() - $ago);
    }

    /**
     * Sends a form as a guest from an address.
     *
     * @param array<mixed> $values
     */
    protected function submit(string $id, array $values, ?string $token = null, ?string $website = null, string $address = '192.0.2.10'): SubmitResult
    {
        return $this->runner()->run(new SubmitForm($id, $values, 'de', $token ?? $this->token($id), $website), Context::guest(address: $address));
    }

    /**
     * @return array<string, string>
     */
    protected static function valid(): array
    {
        return ['name' => 'Ada Lovelace', 'eMailAdresse' => 'ada@example.org', 'message' => "Hallo,\nich hätte eine Frage.", 'privacy' => '1'];
    }

    protected function transport(): MemoryTransport
    {
        $transport = $this->get(Mailer::class)->transport();
        self::assertInstanceOf(MemoryTransport::class, $transport);

        return $transport;
    }

    /**
     * A published page with the given blocks in its main area.
     *
     * @param list<array<string, mixed>> $blocks
     */
    protected function page(string $title, array $blocks = []): string
    {
        $content = ['fields' => ['heading' => $title], 'areas' => ['main' => $blocks]];

        return $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => $title, 'content' => $content]]))->id;
    }

    protected function html(string $path): string
    {
        return (string) $this->get(PageHandler::class)->handle(new ServerRequest('GET', 'https://example.org' . $path))->getBody();
    }
}

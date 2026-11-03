<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Core\Page\PublishPages;
use Frootbox\Forms\Blocks\Form;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Website\SubmitHandler;
use Frootbox\Forms\Website\TokenHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Form::class)]
#[CoversClass(SubmitHandler::class)]
#[CoversClass(TokenHandler::class)]
final class WebsiteTest extends FormsTestCase
{
    private string $form;

    private string $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = $this->contactForm();
        $this->page = $this->page('Kontakt', [['block' => 'frootbox/forms/Form', 'options' => ['form' => $this->form]]]);
        $this->runner()->run(new PublishPages([$this->page]));
    }

    public function testShowsTheFormWithItsFields(): void
    {
        $html = $this->html('/');
        $id = 'form-' . $this->form;

        self::assertStringContainsString('<form class="forms__form" method="post" action="/_forms/submit"', $html);
        self::assertMatchesRegularExpression('~<label class="forms__label" for="' . $id . '-name">Name<span class="forms__required" aria-hidden="true">\*</span></label>~', $html);
        self::assertStringContainsString('<input class="forms__input" type="email" id="' . $id . '-eMailAdresse" name="values[eMailAdresse]" maxlength="254" autocomplete="email" required', $html);
        self::assertStringContainsString('<textarea class="forms__input" id="' . $id . '-message" name="values[message]"', $html);
        self::assertMatchesRegularExpression('~<input type="checkbox" id="' . $id . '-privacy" name="values\[privacy\]" value="1" required~', $html);
        self::assertStringContainsString('name="website" tabindex="-1"', $html, 'The honeypot.');
        self::assertStringContainsString('Mit * markierte Felder sind Pflicht.', $html, 'The texts of the website in German.');
        self::assertStringContainsString('>Senden</button>', $html);
        self::assertStringContainsString('forms.js', $html);
    }

    public function testWithoutJavaScriptLeadsBackToThePage(): void
    {
        $response = $this->send(['values' => self::valid(), '_token' => $this->token($this->form)]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/?fbForm=' . $this->form . '#form-' . $this->form, $response->getHeaderLine('Location'));
        self::assertSame(1, $this->get(Entries::class)->count());
        self::assertStringContainsString('Danke, wir melden uns.', $this->html('/?fbForm=' . $this->form));
        self::assertStringNotContainsString('<form class="forms__form"', $this->html('/?fbForm=' . $this->form), 'The thanks replaces the form.');

        $failed = $this->send(['values' => ['name' => 'Ada'], '_token' => $this->token($this->form)]);
        self::assertSame('/?fbFormError=' . $this->form . '#form-' . $this->form, $failed->getHeaderLine('Location'));
        self::assertStringContainsString('Das Formular konnte nicht gesendet werden.', $this->html('/?fbFormError=' . $this->form));

        $elsewhere = $this->send(['values' => self::valid(), '_token' => $this->token($this->form), '_return' => '//evil.example/']);
        self::assertSame('/?fbForm=' . $this->form . '#form-' . $this->form, $elsewhere->getHeaderLine('Location'), 'Never to another host.');
    }

    public function testWithJavaScriptAnswersJson(): void
    {
        $response = $this->send(['values' => [...self::valid(), 'eMailAdresse' => 'keine'], '_token' => $this->token($this->form)], json: true);

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(['/values/eMailAdresse' => 'Bitte gib eine E-Mail-Adresse ein, z. B. name@example.com.'], $body['errors'] ?? null, 'Errors in the language of the form.');

        $sent = $this->send(['values' => self::valid(), '_token' => $this->token($this->form)], json: true);
        self::assertSame(200, $sent->getStatusCode());
        self::assertSame(['data' => ['message' => 'Danke, wir melden uns.']], json_decode((string) $sent->getBody(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testGivesAFreshToken(): void
    {
        $response = $this->get(TokenHandler::class)->handle(new ServerRequest('GET', 'https://example.org/_forms/token/' . $this->form)->withAttribute('id', $this->form));
        $body = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertIsArray($body);
        self::assertIsArray($body['data'] ?? null);
        self::assertIsString($body['data']['token'] ?? null);
        self::assertMatchesRegularExpression('/^' . time() . '\.[\w-]{40,}$/', $body['data']['token']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(array $body, bool $json = false): ResponseInterface
    {
        $request = new ServerRequest('POST', 'https://example.org/_forms/submit', $json ? ['Accept' => 'application/json'] : [], serverParams: ['REMOTE_ADDR' => '192.0.2.10'])
            ->withParsedBody([
                '_form' => $this->form,
                '_language' => 'de',
                '_page' => $this->page,
                '_return' => '/',
                'website' => '',
                ...$body,
            ]);

        return $this->get(SubmitHandler::class)->handle($request);
    }
}

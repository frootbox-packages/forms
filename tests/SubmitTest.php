<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Auth\TooManyAttemptsException;
use Frootbox\Core\Queue\Worker;
use Frootbox\Core\Schedule\Scheduler;
use Frootbox\Database\Values;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Entry\PruneEntries;
use Frootbox\Forms\Entry\PruneOldEntries;
use Frootbox\Forms\Form\AddField;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Form\UpdateField;
use Frootbox\Forms\Form\UpdateForm;
use Frootbox\Forms\FormsSettings;
use Frootbox\Forms\Website\FormToken;
use Frootbox\Forms\Website\Sendings;
use Frootbox\Forms\Website\Submission;
use Frootbox\Forms\Website\SubmitForm;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubmitForm::class)]
#[CoversClass(Submission::class)]
#[CoversClass(FormToken::class)]
#[CoversClass(Sendings::class)]
#[CoversClass(PruneOldEntries::class)]
final class SubmitTest extends FormsTestCase
{
    public function testKeepsTheEntryAndMailsItToEachRecipient(): void
    {
        $id = $this->contactForm(['team@example.org', 'chefin@example.org']);
        $result = $this->submit($id, self::valid());

        self::assertSame('Danke, wir melden uns.', $result->message);
        $entry = $this->get(Entries::class)->fetchOne();
        self::assertNotNull($entry);
        self::assertSame('de', $entry->language);
        $field = $entry->fields[1];
        ksort($field); // MySQL keeps no order of keys in JSON columns.
        self::assertSame(['key' => 'eMailAdresse', 'label' => 'E-Mail-Adresse', 'type' => 'email', 'value' => 'ada@example.org'], $field);
        self::assertTrue($entry->fields[3]['value'], 'Consent is kept as true.');

        self::assertSame(2, $this->get(Worker::class)->run(5)->done, 'Mails leave through the queue.');
        $sent = $this->transport()->sent();

        self::assertCount(2, $sent);
        $recipients = array_map(static fn(array $mail): array => $mail['message']->to, $sent);
        sort($recipients);
        self::assertSame([['chefin@example.org'], ['team@example.org']], $recipients, 'One mail per recipient, so they do not see each other; jobs of the same millisecond have no order.');
        self::assertSame('ada@example.org', $sent[0]['message']->replyTo, 'An answer goes to the visitor.');
        self::assertStringContainsString('Kontakt', $sent[0]['message']->subject);
        self::assertStringContainsString("Name: Ada Lovelace\n", $sent[0]['message']->text);
        self::assertStringContainsString("Nachricht:\nHallo,\nich hätte eine Frage.\n", $sent[0]['message']->text);
    }

    public function testChecksTheValuesByField(): void
    {
        $id = $this->contactForm();
        $this->runner()->run(new AddField($id, FieldType::Radio, ['de' => ['label' => 'Anrede', 'options' => ['Frau', 'Herr']]], key: 'salutation'));
        $this->runner()->run(new AddField($id, FieldType::Checkboxes, ['de' => ['label' => 'Interessen', 'options' => ['Kaffee', 'Kurse']]], key: 'interests'));
        $this->runner()->run(new AddField($id, FieldType::Date, ['de' => ['label' => 'Wunschtermin']], key: 'date'));
        $this->runner()->run(new PublishForm($id));

        try {
            $this->submit($id, ['name' => ' ', 'eMailAdresse' => 'keine', 'salutation' => 'Divers', 'date' => '2026-02-30']);
            self::fail('Wrong values are refused.');
        } catch (InvalidInputException $error) {
            self::assertSame(['/values/name', '/values/eMailAdresse', '/values/privacy', '/values/salutation', '/values/date'], array_keys($error->errors));
        }

        $this->submit($id, [...self::valid(), 'salutation' => 'Frau', 'interests' => ['Kurse', 'Fremd'], 'date' => '2026-12-01']);
        $fields = array_column($this->get(Entries::class)->fetchOne()->fields ?? [], 'value', 'key');

        self::assertSame(['Kurse'], $fields['interests'], 'Only the options of the form count.');
        self::assertSame('2026-12-01', $fields['date']);
    }

    public function testSpamGetsTheAnswerOfASuccessButNothingHappens(): void
    {
        $id = $this->contactForm();

        $this->submit($id, self::valid(), website: 'https://spam.example');
        $this->submit($id, self::valid(), token: $this->token($id, 1));
        $this->submit($id, self::valid(), token: $this->token($id, 60) . 'x');
        $this->submit($id, self::valid(), token: $this->get(FormToken::class)->issue('01JD0000000000000000000000', time() - 60));
        $result = $this->submit($id, self::valid(), token: '');

        self::assertSame('Danke, wir melden uns.', $result->message);
        self::assertSame(0, $this->get(Entries::class)->count(), 'Neither the honeypot, too fast, a wrong signature nor the token of another form passes.');
        $this->get(Worker::class)->run(5);
        self::assertSame([], $this->transport()->sent());
    }

    public function testAnAddressMaySendAFormFiveTimesInTenMinutes(): void
    {
        $id = $this->contactForm(recipients: []);

        for ($i = 0; $i < Sendings::LIMIT; ++$i) {
            $this->submit($id, self::valid());
        }

        $this->submit($id, self::valid(), address: '198.51.100.7');

        try {
            $this->submit($id, self::valid());
            self::fail('The sixth time is refused.');
        } catch (TooManyAttemptsException $error) {
            self::assertGreaterThan(0, $error->retryAfter);
        }

        self::assertSame(6, $this->get(Entries::class)->count(), 'Another address may still send.');
        $row = $this->connection()->fetchAssociative('SELECT * FROM ' . Sendings::TABLE);
        self::assertIsArray($row);
        self::assertStringNotContainsString('192.0.2.10', json_encode($row, \JSON_THROW_ON_ERROR), 'Only a hash of the address is kept.');
    }

    public function testRefusesAFormThatIsNotOnline(): void
    {
        $id = $this->contactForm(publish: false);

        $this->expectException(InvalidInputException::class);
        $this->submit($id, self::valid());
    }

    public function testTheSchedulerDeletesOldEntries(): void
    {
        $id = $this->contactForm(recipients: []);
        $this->submit($id, self::valid());
        $this->submit($id, self::valid(), address: '198.51.100.7');
        $old = $this->get(Entries::class)->fetchOne();
        self::assertNotNull($old);
        $this->connection()->update(Entries::TABLE, ['created_at' => new DateTimeImmutable('-200 days')->format(Values::DATE_FORMAT)], ['id' => $old->id]);

        $this->get(Scheduler::class)->run();
        self::assertSame(1, $this->get(Entries::class)->count(), 'Older than 180 days goes.');

        self::assertNull(PruneEntries::before(new FormsSettings(keepDays: 0)), 'With 0 they stay.');
    }

    public function testUsesTheTextsOfTheLanguageOfTheVisitor(): void
    {
        $id = $this->contactForm();
        $this->runner()->run(new UpdateForm($id, ['en' => ['title' => 'Contact', 'success' => 'Thanks!']]));
        foreach (['name' => 'Your name', 'eMailAdresse' => 'Email', 'message' => 'Message', 'privacy' => 'I agree to the privacy policy.'] as $key => $label) {
            $this->runner()->run(new UpdateField($id, $key, translations: ['en' => ['label' => $label]]));
        }
        $this->runner()->run(new PublishForm($id));

        $result = $this->runner()->run(new SubmitForm($id, self::valid(), 'en', $this->token($id)), Context::guest(address: '192.0.2.10'));

        self::assertSame('Thanks!', $result->message);
        $entry = $this->get(Entries::class)->fetchOne();
        self::assertSame('en', $entry?->language);
        self::assertSame(['Your name', 'Email'], [$entry->fields[0]['label'], $entry->fields[1]['label']], 'The labels the visitor saw.');
    }
}

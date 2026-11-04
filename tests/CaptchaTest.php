<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Consent\ExternalService;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Queue\Worker;
use Frootbox\Core\Schedule\Scheduler;
use Frootbox\Database\Values;
use Frootbox\Forms\Captcha\Captcha;
use Frootbox\Forms\Captcha\CaptchaRequest;
use Frootbox\Forms\Captcha\Captchas;
use Frootbox\Forms\Captcha\ListCaptchas;
use Frootbox\Forms\Captcha\MathCaptcha;
use Frootbox\Forms\Captcha\ProofHandler;
use Frootbox\Forms\Captcha\ProofOfWork;
use Frootbox\Forms\Captcha\Proofs;
use Frootbox\Forms\Entry\DeleteEntries;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Entry\EntryIds;
use Frootbox\Forms\Entry\ListEntries;
use Frootbox\Forms\Entry\PruneEntries;
use Frootbox\Forms\Entry\ReleaseEntries;
use Frootbox\Forms\Form\FormData;
use Frootbox\Forms\Form\FormStore;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Form\UpdateForm;
use Frootbox\Forms\FormsSettings;
use Frootbox\Forms\Spam\LinkCheck;
use Frootbox\Forms\Spam\SpamRules;
use Frootbox\Forms\Spam\SpamSubject;
use Frootbox\Forms\Spam\WordCheck;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProofOfWork::class)]
#[CoversClass(Proofs::class)]
#[CoversClass(ProofHandler::class)]
#[CoversClass(MathCaptcha::class)]
#[CoversClass(Captchas::class)]
#[CoversClass(ListCaptchas::class)]
#[CoversClass(SpamRules::class)]
#[CoversClass(LinkCheck::class)]
#[CoversClass(WordCheck::class)]
#[CoversClass(ReleaseEntries::class)]
#[CoversClass(DeleteEntries::class)]
#[CoversClass(EntryIds::class)]
#[CoversClass(PruneEntries::class)]
final class CaptchaTest extends FormsTestCase
{
    protected function captcha(): string
    {
        return ProofOfWork::NAME;
    }

    public function testASolvedProofOfWorkReachesTheInboxOnce(): void
    {
        $id = $this->contactForm();
        $proof = $this->proof($id);

        $this->submit($id, self::valid(), captcha: $proof);
        $this->submit($id, self::valid(), captcha: $proof);

        self::assertSame([null, 'The proof of work was used before.'], $this->reasons(), 'A task counts once.');
        self::assertSame(1, $this->get(Worker::class)->run(5)->done, 'Only the entry of the inbox is mailed.');
    }

    public function testWithoutAProofOfWorkTheEntryGoesToTheSpamFolder(): void
    {
        $id = $this->contactForm();
        $task = $this->get(ProofOfWork::class)->task($id, number: 42);

        $result = $this->submit($id, self::valid());
        $this->submit($id, self::valid(), captcha: ['task' => $task['task'], 'number' => '41']);
        $this->submit($id, self::valid(), captcha: ['task' => $this->get(ProofOfWork::class)->task('01JD0000000000000000000000', number: 42)['task'], 'number' => '42']);
        $this->submit($id, self::valid(), captcha: ['task' => $this->get(ProofOfWork::class)->task($id, time() - 2 * ProofOfWork::VALID, 42)['task'], 'number' => '42']);

        self::assertSame('Danke, wir melden uns.', $result->message, 'The visitor sees the thanks.');
        self::assertSame(['The proof of work is missing.', 'The proof of work is wrong.', 'The proof of work is wrong.', 'The proof of work is out of date.'], $this->reasons(), 'A wrong number, the task of another form and an old task do not pass.');
        self::assertSame(0, $this->get(Worker::class)->run(5)->done, 'Spam is not mailed.');
    }

    public function testTheFormFetchesAFreshTask(): void
    {
        $id = $this->contactForm();
        $html = $this->publishedPage($id);

        self::assertStringContainsString('<div class="forms__proof" data-forms-proof="/_forms/proof/' . $id . '" hidden>', $html);
        $response = $this->get(ProofHandler::class)->handle(new ServerRequest('GET', '/_forms/proof/' . $id)->withAttribute('id', $id));
        $body = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertIsArray($body);
        $data = $body['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(['task', 'salt', 'challenge', 'max'], array_keys($data));
        self::assertSame(ProofOfWork::MAX, $data['max']);
        $task = $data['task'];
        self::assertIsString($task);
        self::assertMatchesRegularExpression('/^\d+\.[0-9a-f]{24}\.[0-9a-f]{64}\.[A-Za-z0-9_-]+$/', $task);
    }

    public function testASumIsAnErrorUntilItIsRight(): void
    {
        $id = $this->contactForm();
        $this->runner()->run(new UpdateForm($id, captcha: MathCaptcha::NAME));
        $this->runner()->run(new PublishForm($id));
        $widget = $this->get(MathCaptcha::class)->widget(new CaptchaRequest($id, 'de'), 3, 4);
        self::assertSame(1, preg_match('/name="captcha\[task\]" value="([^"]+)"/', $widget->html, $match));
        self::assertStringContainsString('Wie viel ist 3 plus 4?', $widget->html, 'In the language of the form.');

        foreach ([['task' => $match[1]], ['task' => $match[1], 'answer' => '8']] as $wrong) {
            try {
                $this->submit($id, self::valid(), captcha: $wrong);
                self::fail('A missing or wrong result is refused.');
            } catch (InvalidInputException $error) {
                self::assertSame(['/captcha'], array_keys($error->errors));
            }
        }

        $this->submit($id, self::valid(), captcha: ['task' => $match[1], 'answer' => ' 7 ']);
        self::assertSame([null], $this->reasons());
    }

    public function testEachFormChoosesItsCaptcha(): void
    {
        $id = $this->contactForm();
        $store = $this->get(FormStore::class);
        $captchas = $this->get(Captchas::class);

        $this->runner()->run(new UpdateForm($id, captcha: Captchas::NONE));
        self::assertSame(Captchas::NONE, $store->current($id)->captcha);
        self::assertNull($captchas->of(new FormData(captcha: Captchas::NONE)), 'None at all.');
        $this->runner()->run(new UpdateForm($id, captcha: Captchas::DEFAULT));
        $draft = $store->current($id);
        self::assertNull($draft->captcha, 'Back to the standard.');
        self::assertSame(ProofOfWork::NAME, $captchas->of($draft)?->name);
        self::assertSame(ProofOfWork::NAME, $captchas->of($draft->with(captcha: 'acme/gone/captcha'))?->name, 'An unknown one falls back to the proof of work, never to none.');

        $this->expectException(InvalidInputException::class);
        $this->runner()->run(new UpdateForm($id, captcha: 'acme/gone/captcha'));
    }

    public function testListsTheCaptchasWithTheStandard(): void
    {
        $list = $this->runner()->ask(new ListCaptchas());

        self::assertSame([ProofOfWork::NAME, MathCaptcha::NAME], array_column($list->captchas, 'name'));
        self::assertSame(ProofOfWork::NAME, $list->standard);
    }

    public function testACaptchaOfAnotherProviderLoadsAfterConsent(): void
    {
        $registry = $this->get(Registry::class);
        $registry->add(ExternalService::class, new ExternalService('acme/captcha/checkbox', 'Checkbox', 'Acme Ltd', 'https://acme.example/privacy'));
        $registry->add(Captcha::class, new Captcha('acme/captcha/checkbox', 'Checkbox', ThirdPartyCaptcha::class, service: 'acme/captcha/checkbox'));
        $id = $this->contactForm(publish: false);
        $this->runner()->run(new UpdateForm($id, captcha: 'acme/captcha/checkbox'));
        $this->runner()->run(new PublishForm($id));

        $html = $this->publishedPage($id);

        self::assertStringContainsString('<fb-external class="external" service="acme/captcha/checkbox"><template><script src="https://acme.example/checkbox.js"></script>', $html, 'Its script waits for consent.');

        try {
            $this->submit($id, self::valid());
            self::fail('Without it the form is not sent.');
        } catch (InvalidInputException $error) {
            self::assertSame(['/captcha'], array_keys($error->errors));
        }

        $this->submit($id, self::valid(), captcha: ['token' => 'passed']);
        self::assertSame([null], $this->reasons());
    }

    public function testRulesForTheContentFindSpam(): void
    {
        $id = $this->contactForm();
        $links = 'https://a.example www.b.example http://c.example [url=d] <a href="e">';

        $this->submit($id, [...self::valid(), 'message' => $links], captcha: $this->proof($id));
        $this->submit($id, [...self::valid(), 'message' => 'Drei Links: https://a.example https://b.example https://c.example'], captcha: $this->proof($id), address: '198.51.100.7');

        self::assertSame(['More links than the setting allows.', null], $this->reasons());
        self::assertSame('A word of spam from the settings.', new WordCheck(new FormsSettings(spamWords: 'casino, Krypto-Gewinn'))->check(new SpamSubject($id, 'de', [['key' => 'm', 'label' => 'M', 'type' => 'text', 'value' => 'Ihr KRYPTO-GEWINN wartet']])));
        self::assertNull(new WordCheck(new FormsSettings(spamWords: 'casino'))->check(new SpamSubject($id, 'de', [['key' => 'm', 'label' => 'M', 'type' => 'text', 'value' => 'Casinostraße 4']])), 'Only whole words.');
    }

    public function testAnEntryLeavesTheSpamFolderAndIsMailedThen(): void
    {
        $id = $this->contactForm();
        $this->submit($id, self::valid());
        $this->submit($id, self::valid(), captcha: $this->proof($id));
        $spam = $this->runner()->ask(new ListEntries($id, spam: true));

        self::assertSame([1, 1, 1], [\count($spam->entries), $spam->spam, $spam->inbox]);
        self::assertSame(1, $this->get(FormStore::class)->details($id)->entries, 'The form counts its inbox.');
        self::assertSame(1, $this->get(Worker::class)->run(5)->done);

        self::assertSame(1, $this->runner()->run(new ReleaseEntries([$spam->entries[0]->id, $spam->entries[0]->id])));
        self::assertSame([null, null], $this->reasons());
        self::assertSame(1, $this->get(Worker::class)->run(5)->done, 'Mailed when it leaves the spam folder.');
        self::assertSame(0, $this->runner()->run(new ReleaseEntries([$spam->entries[0]->id])), 'Once only.');
    }

    public function testAnIdWithoutAnEntryChangesNothing(): void
    {
        $id = $this->contactForm();
        $this->submit($id, self::valid());
        $entry = $this->runner()->ask(new ListEntries($id, spam: true))->entries[0]->id;

        foreach ([new ReleaseEntries([$entry, '']), new DeleteEntries(['01JZZZZZZZZZZZZZZZZZZZZZZZ', $entry])] as $action) {
            try {
                $this->runner()->run($action);
                self::fail('An id without an entry fails the action.');
            } catch (InvalidInputException $exception) {
                self::assertSame([$action instanceof ReleaseEntries ? '/ids/1' : '/ids/0'], array_keys($exception->errors));
                self::assertSame('There is no such entry.', $exception->errors[$action instanceof ReleaseEntries ? '/ids/1' : '/ids/0']);
            }
        }

        $after = $this->runner()->ask(new ListEntries($id, spam: true));
        self::assertSame([1, 0], [$after->spam, $after->inbox], 'Neither released nor deleted.');
    }

    public function testSpamGoesAfterThirtyDays(): void
    {
        $id = $this->contactForm(recipients: []);
        $this->submit($id, self::valid());
        $this->submit($id, self::valid(), captcha: $this->proof($id));
        $this->connection()->update(Entries::TABLE, ['created_at' => new DateTimeImmutable('-40 days')->format(Values::DATE_FORMAT)], ['form_id' => $id]);

        $this->get(Scheduler::class)->run();

        self::assertSame([null], $this->reasons(), 'The inbox stays 180 days.');
        self::assertEquals([['createdAt <' => new DateTimeImmutable('2026-01-01 00:00')->modify('-10 days')]], PruneEntries::where(new FormsSettings(keepDays: 10), new DateTimeImmutable('2026-01-01 00:00')), 'A shorter time takes spam along.');
    }

    /**
     * A solved task of the proof of work.
     *
     * @return array<string, string>
     */
    private function proof(string $id): array
    {
        return ['task' => $this->get(ProofOfWork::class)->task($id, number: 1234)['task'], 'number' => '1234'];
    }

    /**
     * Why each entry is spam, oldest first; null for the inbox.
     *
     * @return list<string|null>
     */
    private function reasons(): array
    {
        return array_map(static fn($entry): ?string => $entry->spam, $this->get(Entries::class)->fetch(['order' => ['id' => 'asc']])->all());
    }

    /**
     * The start page with the form, as a guest sees it.
     */
    private function publishedPage(string $form): string
    {
        $this->runner()->run(new PublishPages([$this->page('Kontakt', [['block' => 'frootbox/forms/Form', 'options' => ['form' => $form]]])]));

        return $this->html('/');
    }
}

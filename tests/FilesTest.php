<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Core\Action\Context;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Queue\Worker;
use Frootbox\Forms\Entry\DeleteEntries;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Form\AddField;
use Frootbox\Forms\Form\CreateForm;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Website\FormFiles;
use Frootbox\Forms\Website\Submission;
use Frootbox\Forms\Website\SubmitHandler;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(FormFiles::class)]
#[CoversClass(Submission::class)]
#[CoversClass(SubmitHandler::class)]
final class FilesTest extends FormsTestCase
{
    private const string PDF = "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n";

    private string $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = $this->runner()->run(new CreateForm(['de' => ['title' => 'Bewerbung']], ['jobs@example.org']))->id;
        $this->runner()->run(new AddField($this->form, FieldType::Text, ['de' => ['label' => 'Name']], true, key: 'name'));
        $this->runner()->run(new AddField($this->form, FieldType::Files, ['de' => ['label' => 'Lebenslauf und Zeugnisse']], true, key: 'documents'));
        $this->runner()->run(new PublishForm($this->form));
        $page = $this->page('Bewerbung', [['block' => 'frootbox/forms/Form', 'options' => ['form' => $this->form]]]);
        $this->runner()->run(new PublishPages([$page]));
    }

    public function testTheFieldTakesSeveralFiles(): void
    {
        $html = $this->html('/');
        $id = 'form-' . $this->form . '-documents';

        self::assertStringContainsString('action="/_forms/submit" enctype="multipart/form-data"', $html, 'Without JavaScript, too.');
        self::assertStringContainsString('<input class="forms__input forms__input--files" type="file" id="' . $id . '" name="files[documents][]" multiple accept=".pdf,.docx,.odt,.txt,.jpg,.jpeg,.png,.webp" required', $html);
        self::assertStringContainsString('bis zu 5 Dateien mit je höchstens 10 MB', $html);
    }

    public function testKeepsTheFilesWithTheEntryAndNamesThemInTheMail(): void
    {
        $response = $this->send(['name' => 'Ada'], [$this->file(self::PDF, 'Lebenslauf.pdf'), $this->file("Zeugnis\n", 'Zeugnis.txt')]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $entry = $this->get(Entries::class)->fetchOne();
        self::assertNotNull($entry);
        $field = $entry->fields[1];
        self::assertSame(['Lebenslauf.pdf', 'Zeugnis.txt'], $field['value']);
        $files = $field['files'] ?? [];
        self::assertCount(2, $files);
        self::assertMatchesRegularExpression('/^[0-9a-z]{26}\.pdf$/', $files[0]['id']);
        self::assertSame(\strlen(self::PDF), $files[0]['size']);
        $path = $this->get(FormFiles::class)->path($entry->id, $files[0]['id']);
        self::assertNotNull($path);
        self::assertSame($this->get(ProjectPaths::class)->storage('private/forms/' . $entry->id . '/' . $files[0]['id']), $path, 'Below storage/private, never public.');
        self::assertSame(self::PDF, file_get_contents($path));

        $this->get(Worker::class)->run(5);
        $mail = $this->transport()->sent()[0]['message'];
        self::assertStringContainsString("Lebenslauf und Zeugnisse: Lebenslauf.pdf, Zeugnis.txt\n", $mail->text);
        self::assertStringContainsString('Die Dateien liegen bei der Einsendung im Backend.', $mail->text, 'No attachment: the files stay on the server.');
    }

    public function testRefusesMissingWrongAndTooManyFiles(): void
    {
        self::assertSame(['/values/documents' => 'Bitte wähle eine Datei aus.'], $this->errors($this->send(['name' => 'Ada'], [])));
        self::assertSame(['/values/documents' => 'Bitte wähle PDF-, Word-, OpenDocument- oder Textdokumente oder Bilder (JPEG, PNG, WebP).'], $this->errors($this->send(['name' => 'Ada'], [$this->file("MZ\x90\x00binary", 'Lebenslauf.pdf')])), 'The content counts, not the name.');
        self::assertSame(['/values/documents' => 'Bitte wähle höchstens 5 Dateien aus.'], $this->errors($this->send(['name' => 'Ada'], array_map(fn(int $n): UploadedFile => $this->file(self::PDF, $n . '.pdf'), range(1, 6)))));
        self::assertSame(['/values/documents' => 'Eine Datei kam nicht an. Jede darf höchstens 10 MB groß sein.'], $this->errors($this->send(['name' => 'Ada'], [new UploadedFile('', 0, \UPLOAD_ERR_INI_SIZE, 'Riesig.pdf')])));
        self::assertSame(0, $this->get(Entries::class)->count());
    }

    public function testTheFilesOfADeletedEntryGoADayLater(): void
    {
        $this->send(['name' => 'Ada'], [$this->file(self::PDF, 'Lebenslauf.pdf')]);
        $entry = $this->get(Entries::class)->fetchOne();
        self::assertNotNull($entry);
        $path = $this->get(FormFiles::class)->path($entry->id, ($entry->fields[1]['files'] ?? [])[0]['id'] ?? '');
        self::assertNotNull($path);
        $files = $this->get(FormFiles::class);
        $now = time();

        self::assertSame(0, $files->sweep($this->get(Entries::class), $now), 'An entry keeps its files.');
        $this->runner()->run(new DeleteEntries([$entry->id]), Context::system());
        self::assertSame(0, $files->sweep($this->get(Entries::class), $now), 'Deleting can be undone, so the files wait.');
        self::assertFileExists($path);
        self::assertSame(1, $files->sweep($this->get(Entries::class), $now + 86400));
        self::assertFileDoesNotExist($path);
    }

    private function file(string $content, string $name): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'form');
        file_put_contents($path, $content);

        return new UploadedFile($path, \strlen($content), \UPLOAD_ERR_OK, $name);
    }

    /**
     * @param array<string, string> $values
     * @param list<UploadedFile>    $files
     */
    private function send(array $values, array $files): ResponseInterface
    {
        $request = new ServerRequest('POST', 'https://example.org/_forms/submit', ['Accept' => 'application/json'], serverParams: ['REMOTE_ADDR' => '192.0.2.20'])
            ->withParsedBody(['_form' => $this->form, '_language' => 'de', '_token' => $this->token($this->form), '_return' => '/', 'values' => $values])
            ->withUploadedFiles(['files' => ['documents' => $files]]);

        return $this->get(SubmitHandler::class)->handle($request);
    }

    /**
     * @return array<mixed>
     */
    private function errors(ResponseInterface $response): array
    {
        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($body) && \is_array($body['errors'] ?? null) ? $body['errors'] : [];
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Tests;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Component\BlockSummary;
use Frootbox\Forms\Entry\Entries;
use Frootbox\Forms\Form\AddField;
use Frootbox\Forms\Form\CreateForm;
use Frootbox\Forms\Form\DeleteForm;
use Frootbox\Forms\Form\DiscardForm;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\FormChoices;
use Frootbox\Forms\Form\FormEditor;
use Frootbox\Forms\Form\FormStatus;
use Frootbox\Forms\Form\ListForms;
use Frootbox\Forms\Form\MoveField;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Form\RemoveField;
use Frootbox\Forms\Form\ShowForm;
use Frootbox\Forms\Form\UpdateField;
use Frootbox\Forms\Form\UpdateForm;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormEditor::class)]
#[CoversClass(CreateForm::class)]
#[CoversClass(AddField::class)]
#[CoversClass(PublishForm::class)]
final class FormsTest extends FormsTestCase
{
    public function testBuildsAFormFieldByField(): void
    {
        $id = $this->contactForm(publish: false);
        $form = $this->runner()->ask(new ShowForm($id));

        self::assertSame(FormStatus::Draft, $form->status);
        self::assertSame(['name', 'eMailAdresse', 'message', 'privacy'], array_map(static fn($field): string => $field->key, $form->current()->fields), 'The key comes from the label if left out.');
        self::assertSame(['team@example.org'], $form->current()->recipients);
        self::assertNull($this->get(\Frootbox\Forms\Form\FormStore::class)->published($id), 'Nothing is online before publishing.');

        $this->runner()->run(new MoveField($id, 'message', 0));
        $this->runner()->run(new UpdateField($id, 'eMailAdresse', translations: ['de' => ['hint' => 'Für die Antwort']], newKey: 'email'));
        $this->runner()->run(new RemoveField($id, 'name'));
        $fields = $this->runner()->ask(new ShowForm($id))->current()->fields;

        self::assertSame(['message', 'email', 'privacy'], array_map(static fn($field): string => $field->key, $fields));
        self::assertSame('E-Mail-Adresse', $fields[1]->text('de')->label, 'Texts left out stay.');
        self::assertSame('Für die Antwort', $fields[1]->text('de')->hint);
        self::assertTrue($fields[2]->isRequired(), 'Consent is always required.');
    }

    public function testPublishesOnlyACompleteForm(): void
    {
        $id = $this->runner()->run(new CreateForm(['de' => ['title' => 'Anmeldung'], 'en' => ['title' => '']]))->id;

        try {
            $this->runner()->run(new PublishForm($id));
            self::fail('A form without fields is not published.');
        } catch (InvalidInputException $error) {
            self::assertArrayHasKey('/fields', $error->errors);
            self::assertArrayHasKey('/translations/en/title', $error->errors);
        }

        $this->runner()->run(new UpdateForm($id, ['en' => null]));
        $this->runner()->run(new AddField($id, FieldType::Select, ['de' => ['label' => 'Kurs']], key: 'course'));

        try {
            $this->runner()->run(new PublishForm($id));
            self::fail('A choice without options is not published.');
        } catch (InvalidInputException $error) {
            self::assertSame(['/fields/0/translations/de/options'], array_keys($error->errors));
        }

        $this->runner()->run(new UpdateField($id, 'course', translations: ['de' => ['options' => ['Latte Art', 'Rösten', 'Latte Art', '']]]));
        $form = $this->runner()->run(new PublishForm($id));

        self::assertSame(FormStatus::Published, $form->status);
        self::assertSame(['Latte Art', 'Rösten'], $form->current()->fields[0]->text('de')->options, 'Options are unique and not empty.');
        self::assertSame([['value' => $id, 'label' => 'Anmeldung']], $this->runner()->ask(new FormChoices()), 'Published forms are the choices of the block.');
    }

    public function testRefusesWrongInput(): void
    {
        $id = $this->contactForm(publish: false);

        foreach ([
            '/recipients/0' => static fn() => new CreateForm(['de' => ['title' => 'X']], ['keine Adresse']),
            '/translations/fr' => static fn() => new CreateForm(['fr' => ['title' => 'Contact']]),
            '/key' => static fn() => new AddField($id, FieldType::Text, ['de' => ['label' => 'Noch ein Name']], key: 'name'),
            '/newKey' => static fn() => new UpdateField($id, 'message', newKey: 'name'),
        ] as $pointer => $action) {
            try {
                $this->runner()->run($action());
                self::fail('Refused: ' . $pointer);
            } catch (InvalidInputException $error) {
                self::assertArrayHasKey($pointer, $error->errors);
            }
        }
    }

    public function testListsDiscardsAndDeletes(): void
    {
        $contact = $this->contactForm();
        $this->runner()->run(new UpdateForm($contact, ['de' => ['title' => 'Kontakt neu']]));
        $draft = $this->runner()->run(new CreateForm(['de' => ['title' => 'Anmeldung']]))->id;

        $list = $this->runner()->ask(new ListForms());
        self::assertSame(['Anmeldung', 'Kontakt neu'], array_map(static fn($form): string => $form->title, $list));
        self::assertSame(FormStatus::Changed, $list[1]->status);
        self::assertSame(4, $list[1]->fields);

        self::assertSame(FormStatus::Published, $this->runner()->run(new DiscardForm($contact))?->status);
        self::assertNull($this->runner()->run(new DiscardForm($draft)), 'A draft never published is gone.');

        $this->submit($contact, self::valid());
        self::assertSame(1, $this->get(Entries::class)->count());
        $this->runner()->run(new DeleteForm($contact));

        self::assertSame([], $this->runner()->ask(new ListForms()));
        self::assertSame(0, $this->get(Entries::class)->count(), 'The entries go with the form.');
    }

    public function testTheBlockOffersThePublishedForms(): void
    {
        $id = $this->contactForm();
        $blocks = $this->runner()->ask(new \Frootbox\Core\Component\ListBlocks());
        $form = array_values(array_filter($blocks, static fn(BlockSummary $block): bool => 'frootbox/forms/Form' === $block->name))[0] ?? null;

        self::assertNotNull($form);
        $option = array_values(array_filter($form->variants[0]->options, static fn($option): bool => 'form' === $option->name))[0] ?? null;
        self::assertSame([['value' => $id, 'label' => 'Kontakt']], $option?->choices);
    }
}

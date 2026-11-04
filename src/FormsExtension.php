<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms;

use Frootbox\Backend\Screen;
use Frootbox\Backend\SearchCommand;
use Frootbox\Backend\SearchLink;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Forms\Backend\EntriesHandler;
use Frootbox\Forms\Backend\ExportHandler;
use Frootbox\Forms\Backend\FileHandler;
use Frootbox\Forms\Backend\FormHandler;
use Frootbox\Forms\Backend\FormsHandler;
use Frootbox\Forms\Captcha\Captcha;
use Frootbox\Forms\Captcha\ListCaptchas;
use Frootbox\Forms\Captcha\MathCaptcha;
use Frootbox\Forms\Captcha\ProofHandler;
use Frootbox\Forms\Captcha\ProofOfWork;
use Frootbox\Forms\Entry\DeleteEntries;
use Frootbox\Forms\Entry\ListEntries;
use Frootbox\Forms\Entry\PruneEntries;
use Frootbox\Forms\Entry\PruneOldEntries;
use Frootbox\Forms\Entry\ReleaseEntries;
use Frootbox\Forms\Entry\ShowEntry;
use Frootbox\Forms\Form\AddField;
use Frootbox\Forms\Form\CreateForm;
use Frootbox\Forms\Form\DeleteForm;
use Frootbox\Forms\Form\DiscardForm;
use Frootbox\Forms\Form\FormChoices;
use Frootbox\Forms\Form\FormStore;
use Frootbox\Forms\Form\ListForms;
use Frootbox\Forms\Form\MoveField;
use Frootbox\Forms\Form\PublishForm;
use Frootbox\Forms\Form\RemoveField;
use Frootbox\Forms\Form\ShowForm;
use Frootbox\Forms\Form\UpdateField;
use Frootbox\Forms\Form\UpdateForm;
use Frootbox\Forms\Spam\LinkCheck;
use Frootbox\Forms\Spam\SpamChecks;
use Frootbox\Forms\Spam\WordCheck;
use Frootbox\Forms\Website\ForgetFiles;
use Frootbox\Forms\Website\SubmitForm;
use Frootbox\Forms\Website\SubmitHandler;
use Frootbox\Forms\Website\TokenHandler;

/**
 * Forms (decision 0222): built in the backend, shown by the block
 * "Form", sent through a public action with spam protection, kept as
 * entries and mailed through the queue. Captchas and rules for the
 * content are points other extensions plug into (decision 0261).
 */
final class FormsExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->action(CreateForm::class);
        $registry->action(UpdateForm::class);
        $registry->action(AddField::class);
        $registry->action(UpdateField::class);
        $registry->action(MoveField::class);
        $registry->action(RemoveField::class);
        $registry->action(PublishForm::class);
        $registry->action(DiscardForm::class);
        $registry->action(DeleteForm::class);
        $registry->query(ListForms::class);
        $registry->query(ShowForm::class);
        $registry->query(FormChoices::class);
        $registry->query(ListCaptchas::class);

        $registry->action(SubmitForm::class);
        $registry->query(ListEntries::class);
        $registry->query(ShowEntry::class);
        $registry->action(DeleteEntries::class);
        $registry->action(PruneEntries::class);
        $registry->action(ReleaseEntries::class);

        $registry->add(Captcha::class, new Captcha(ProofOfWork::NAME, 'Proof of work', ProofOfWork::class, description: 'Invisible: the browser of the visitor solves a task for a moment. Without JavaScript an entry goes to the spam folder.'));
        $registry->add(Captcha::class, new Captcha(MathCaptcha::NAME, 'Sum to solve', MathCaptcha::class, description: 'Visitors solve a small sum, also without JavaScript.'));
        $registry->add(SpamChecks::class, new SpamChecks(LinkCheck::class));
        $registry->add(SpamChecks::class, new SpamChecks(WordCheck::class));

        $registry->searchSource(FormSource::class);
        $registry->settings(FormsSettings::class);
        $registry->listen(ScheduleRan::class, PruneOldEntries::class);
        $registry->listen(ScheduleRan::class, ForgetFiles::class);

        $registry->post(SubmitHandler::ROUTE, SubmitHandler::class, 'frootbox.forms.submit');
        $registry->get(TokenHandler::ROUTE, TokenHandler::class, 'frootbox.forms.token');
        $registry->get(ProofHandler::ROUTE, ProofHandler::class, 'frootbox.forms.proof');

        if (class_exists(Screen::class)) {
            $this->backend($registry);
        }
    }

    /**
     * The screens in the backend, only if it is installed.
     */
    private function backend(Registry $registry): void
    {
        $registry->mount('backend.path', '/cms')
            ->get('/forms', FormsHandler::class, 'frootbox.forms.forms')
            ->get('/forms/new', FormHandler::class, 'frootbox.forms.forms.new')
            ->get('/forms/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}', FormHandler::class, 'frootbox.forms.forms.show')
            ->get('/forms/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}/entries', EntriesHandler::class, 'frootbox.forms.entries')
            ->get('/forms/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}/entries.csv', ExportHandler::class, 'frootbox.forms.entries.export')
            ->get('/forms/entries/{entry:[0-7][0-9A-HJKMNP-TV-Z]{25}}/files/{file:[0-9a-z]{26}\.[a-z]{3,4}}', FileHandler::class, 'frootbox.forms.entries.file');

        $registry->add(Screen::class, new Screen('Forms', 'frootbox.forms.forms', Screen::CONTENT, 'inbox', FormsHandler::PERMISSION, order: 25));
        $registry->add(SearchLink::class, new SearchLink(FormStore::TYPE, 'frootbox.forms.forms.show', 'id', 'inbox'));
        $registry->add(SearchCommand::class, new SearchCommand('New form', 'frootbox.forms.forms.new', 'frootbox/forms/form.create'));
    }
}

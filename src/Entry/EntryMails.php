<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Entry;

use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Mail\Message as Mail;
use Frootbox\Core\Mail\SendMail;
use Frootbox\Core\Queue\Queue;
use Frootbox\Forms\Form\FieldType;
use Frootbox\Forms\Form\FormData;

/**
 * Mails an entry to each recipient of its form through the queue
 * (decision 0222), after sending or when it leaves the spam folder
 * (decision 0261).
 */
final readonly class EntryMails
{
    public function __construct(
        private Queue $queue,
        private Translator $translator,
        private Languages $languages,
    ) {}

    public function send(FormData $form, Entry $entry): void
    {
        foreach ($form->recipients as $recipient) {
            $this->queue->push(SendMail::of($this->mail($form, $entry, $recipient)));
        }
    }

    /**
     * The entry as a plain text mail in the language of the backend; an
     * answer goes to the first email the visitor gave.
     */
    private function mail(FormData $form, Entry $entry, string $recipient): Mail
    {
        $language = $this->translator->standard();
        $packages = ['frootbox/forms'];
        $lines = [];
        $files = false;
        $replyTo = null;

        foreach ($entry->fields as $field) {
            $value = Entry::text($field['value'], $this->translator->in($language, 'yes', $packages), $this->translator->in($language, 'no', $packages));
            $lines[] = $field['label'] . ':' . (str_contains($value, "\n") ? "\n" . $value . "\n" : ' ' . $value);
            $files = $files || [] !== ($field['files'] ?? []);

            if (null === $replyTo && FieldType::Email->value === $field['type'] && \is_string($field['value']) && '' !== $field['value']) {
                $replyTo = $field['value'];
            }
        }

        // Files stay on the server: a mail is no place for a CV (decision 0230).
        if ($files) {
            $lines[] = '';
            $lines[] = $this->translator->in($language, 'The files are with the entry in the backend.', $packages);
        }

        $title = $form->text($this->languages->default()->code)->title;

        return new Mail(
            [$recipient],
            null === $entry->aboutTitle
                ? $this->translator->in($language, 'New entry: {form}', $packages, ['form' => $title])
                : $this->translator->in($language, 'New entry: {form} – {about}', $packages, ['form' => $title, 'about' => $entry->aboutTitle]),
            implode("\n", $lines) . "\n",
            $replyTo,
        );
    }
}

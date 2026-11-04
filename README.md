# frootbox/forms

Forms for the Frootbox CMS: build forms from field types in the
backend, show them with the block **Form** (`frootbox/forms/Form`) on
any page, keep the entries and mail them to recipients through the
queue. Spam protection works without a captcha.

## Field types

`text`, `email`, `phone`, `number`, `date`, `textarea`, `select`,
`radio`, `checkboxes`, `checkbox`, `consent` (always required) and
`files`. Each field has a key, a label, a hint and, for choices, options
by language.

A consent may link to a page, mostly the privacy policy:
`form.field-add` and `form.field-update` take `link`, a target such as
`page:01J…` or an address. The text in `[square brackets]` of the label
becomes the link; without brackets the theme puts a link "Privacy
policy" after the label. Mails, entries and the export show the label
without the brackets.

## Files

A field `files` takes up to 5 files of at most 10 MB each, 20 MB
together: PDF, DOCX, ODT, plain text, JPEG, PNG and WebP, checked by
their content. They are kept in `storage/private/forms/<entry>/`, never
mailed, and downloaded only in the backend at
`/cms/forms/entries/{entry}/files/{file}` with the permission
`frootbox/forms/entry.list`. A day after an entry is deleted the
scheduler removes its files, so undoing the deletion keeps them.

## What an entry is about

On the address of an object, e.g. a job at `frootbox/jobs/job:<id>`, the
block sends that target as `_about`. The entry keeps it with the
published title of the object (`about`, `aboutTitle`); the mail subject
and the CSV export name it.

Another block can show a form with
`render_block('frootbox/forms/Form', {form: id})`.

## Actions and queries

Everything goes through actions, also from the REST API and the AI:
`frootbox/forms/form.create`, `.update`, `.field-add`, `.field-update`,
`.field-move`, `.field-remove`, `.publish`, `.discard`, `.delete`, the
queries `frootbox/forms/form.list`, `.show` and `.choices`, and for the
entries `frootbox/forms/entry.list`, `.show`, `.delete` and `.prune`.
The role "editor" gets `frootbox/forms/form.*` on installation; the
entries stay with the admins.

Visitors send a form with the public action `frootbox/forms/form.submit`.
The website posts to `/_forms/submit`; with JavaScript it answers JSON,
without it leads back to the page with `?fbForm=<id>` or
`?fbFormError=<id>`.

## Spam protection

- a hidden field that people leave empty,
- the time the form was shown, signed with `APP_SECRET`: sent within
  3 seconds counts as a bot,
- at most 5 entries per address and form in 10 minutes, counted with a
  keyed hash of the address.

Spam gets the answer of a success but is neither kept nor sent.

## Settings

`keepDays` (default 180, 0 keeps entries until someone deletes them):
older entries are deleted when the scheduler runs. Set it in the
backend or in `config/extensions/frootbox/forms.yaml`.

## Mail

Each recipient gets a plain text mail through the job `mail.send` of
the core; answering it goes to the first email field. The transport
comes from `MAILER_DSN` in `.env`.

## License

MIT

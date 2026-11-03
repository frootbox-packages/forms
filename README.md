# frootbox/forms

Forms for the Frootbox CMS: build forms from field types in the
backend, show them with the block **Form** (`frootbox/forms/Form`) on
any page, keep the entries and mail them to recipients through the
queue. Spam protection works without a captcha.

## Field types

`text`, `email`, `phone`, `number`, `date`, `textarea`, `select`,
`radio`, `checkboxes`, `checkbox` and `consent` (always required). Each
field has a key, a label, a hint and, for choices, options by language.

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

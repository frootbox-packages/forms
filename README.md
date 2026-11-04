# frootbox/forms

Forms for the Frootbox CMS: build forms from field types in the
backend, show them with the block **Form** (`frootbox/forms/Form`) on
any page, keep the entries and mail them to recipients through the
queue. Spam protection comes in layers: invisible checks, a captcha
of your choice, rules for the content and a spam folder.

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

Every form has three invisible checks; what fails them gets the answer
of a success but is neither kept nor sent:

- a hidden field that people leave empty,
- the time the form was shown, signed with `APP_SECRET`: sent within
  3 seconds counts as a bot,
- at most 5 entries per address and form in 10 minutes, counted with a
  keyed hash of the address.

Then the captcha of the form and the rules for the content check the
entry. A captcha may find it **wrong** (the visitor sees an error, e.g.
a wrong sum) or **suspicious**; a suspicious entry and one a rule
finds go to the spam folder, without a mail. In the backend it moves
back to the inbox with `frootbox/forms/entry.release`, which mails it
then. Spam is deleted after 30 days.

Two captchas come with the module:

- `frootbox/forms/proof-of-work`, the default: invisible. When the
  visitor enters the form, the browser fetches a task from
  `/_forms/proof/<id>` and searches the number up to 200,000 whose
  SHA-256 with the salt gives the hash of the task. A task is signed,
  valid for an hour and counts once. Without JavaScript the entry goes
  to the spam folder.
- `frootbox/forms/math`: a sum to solve, also without JavaScript.

Each form chooses one in the tab **Spam protection**, or none, or the
standard of the website (setting `captcha`).

### A captcha of your own

An extension registers a captcha with a provider from the container:

```php
$registry->add(ExternalService::class, new ExternalService(
    'acme/captcha/turnstile', 'Cloudflare Turnstile', 'Cloudflare, Inc.',
    'https://www.cloudflare.com/privacypolicy/',
));
$registry->add(Captcha::class, new Captcha(
    'acme/captcha/turnstile', 'Cloudflare Turnstile', TurnstileCaptcha::class,
    service: 'acme/captcha/turnstile',
    description: 'A check by Cloudflare, loaded after consent.',
));
```

`TurnstileCaptcha` implements `Frootbox\Forms\Captcha\CaptchaProvider`:
`widget()` returns the markup inside the form, `verify()` checks what
came back in fields named `captcha[…]` and returns
`CaptchaResult::passed()`, `::wrong($message)` or
`::suspicious($reason)`. With a `service` the markup sits behind
`external()` of the core and loads only after consent; without
consent the form cannot be sent, so `verify()` returns `wrong()` with
a hint.

A captcha that needs keys implements `ConfigurableCaptcha` with
`ready()`: until it is ready, `frootbox/forms/captcha.list` says so, the
backend greys it out, `form.update` refuses it and a form that has it
falls back to the proof of work.

In the browser the script of the forms fires two events at the form.
`frootbox:forms-sending` comes before sending; a captcha pushes a
promise to `event.detail.wait`, and sending waits for it, e.g. to fetch
a token. `frootbox:forms-failed` comes after a failed try, with the
errors by pointer in `event.detail.errors`, e.g. `/captcha`.
`frootbox/captcha-recaptcha` is a complete example.

### Rules for the content

Two rules come with the module: more links than the setting `maxLinks`
(default 3) and a word of the setting `spamWords` (separated by commas).
An extension adds one with
`$registry->add(SpamChecks::class, new SpamChecks(AcmeCheck::class))`;
`AcmeCheck` implements `Frootbox\Forms\Spam\SpamCheck` and returns the
reason in English, or null.

## Settings

- `keepDays` (default 180, 0 keeps entries until someone deletes them):
  older entries are deleted when the scheduler runs.
- `captcha` (default `frootbox/forms/proof-of-work`): the captcha of
  forms that choose none, a name or `none`.
- `maxLinks` (default 3) and `spamWords` (empty): rules for the content.

Set them in the backend or in `config/extensions/frootbox/forms.yaml`.

## Mail

Each recipient gets a plain text mail through the job `mail.send` of
the core; answering it goes to the first email field. The transport
comes from `MAILER_DSN` in `.env`.

## Source and issues

This package is developed in the [Frootbox monorepo](https://github.com/Frootbox/frootbox)
and mirrored here read-only. Questions, bugs and ideas go there, see the
[documentation](https://github.com/Frootbox/frootbox/tree/main/documentation).

## License

MIT

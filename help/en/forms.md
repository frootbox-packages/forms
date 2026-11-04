---
title: Forms
about: [screen:frootbox.forms.forms, action:frootbox/forms/form.create]
permission: frootbox/forms/form.list
order: 65
---
**Forms** lets visitors write to you: a contact form, a registration
for a course or a callback request. You build a form here and show it
on any page with the block **Form**.

## Build a form

Choose **New form**, give it a title and, if you like, the email
addresses that get every entry. Then add its fields one by one with
**Add a field**: a short or long text, an email, a phone number, a
number, a date, a drop-down list, one of several, several of several, a
checkbox, a consent or files. Each field has a label, a hint if you like and,
for choices, its options, one per line. Arrows change the order.

Link a consent to a page under **Link**, mostly the privacy policy. Put
the text of the link in square brackets in the label, such as "I have
read the [privacy policy].". Without brackets the link follows the
label. Entries and mails show the label without the brackets.

The screen of a form has tabs: **Entries** with their number,
**Texts**, **Fields**, **Recipients**, **Spam protection** and **More** to delete it. A dot on a tab shows that something there is
not saved yet. Choose the language at the top next to the status; it
holds for all tabs.

The form stays a draft until you choose **Publish**. Changes to a
published form are a draft as well; visitors see them once you publish
again.

## Show it on the website

Add the block **Form** to a page and choose the form in its options.
The same form can stand on several pages; its entries all arrive in one
place.

## Entries

Each entry is kept with the form and sent by mail to the recipients.
An answer to that mail goes to the first email address the visitor
gave. Under **Entries** you read them, delete them or export them as a
CSV file for a spreadsheet. How long entries stay is a setting of the
forms, 180 days unless you change it.

## Files

A field **Files** takes up to five files, such as a CV: PDF, Word,
OpenDocument, text or images, at most 10 MB each. The files do not go
with the mail, it only names them. You download them under **Entries**,
and only people who may see the entries can. When an entry goes, its
files go too.

When a form stands on the page of a job or another entry, the entry
shows under **Regarding** where it came from.

## Spam

A field people never see, the time it takes to fill in a form and a
limit of five entries in ten minutes protect every form. What fails
them is neither kept nor sent.

On top comes a **captcha** you choose in the tab **Spam protection**:

- **Proof of work**, the standard: invisible. The browser of the
  visitor solves a task for a moment before the form is sent. Whoever
  has no JavaScript lands in the spam folder.
- **Sum to solve**: visitors solve a small sum, also without
  JavaScript.
- **No captcha**, or the **standard of the website** from the settings
  of the forms.

Other providers such as reCAPTCHA come with modules of their own. They
load only once the visitor agrees.

What looks like spam, e.g. too many links or a word of the **words of
spam** in the settings, goes to the folder **Spam** under **Entries**
without a mail. It says why. With **Not spam** you move an entry to the
inbox, and the mail goes out then. Spam is deleted after 30 days.

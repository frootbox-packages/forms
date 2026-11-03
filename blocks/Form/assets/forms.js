/*
 * SPDX-License-Identifier: MIT
 */

// Sends forms of the forms module with fetch (decision 0222): errors show
// at the fields, the thanks replaces the form. Without JavaScript the form
// is sent as usual and the page comes back with the result.

const forms = document.querySelectorAll('form[data-forms]');

for (const form of forms) {
  refreshToken(form);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    send(form);
  });
}

// The page may come from the cache, so the time the form was shown is
// fetched anew. If that fails the token of the page stays.
async function refreshToken(form) {
  try {
    const response = await fetch(form.dataset.formsToken, { headers: { Accept: 'application/json' }, cache: 'no-store' });

    if (response.ok) {
      const body = await response.json();
      form.elements.namedItem('_token').value = body.data.token;
    }
  } catch {
    // Keep the token of the page.
  }
}

async function send(form) {
  const button = form.querySelector('button[type="submit"]');

  if (button.getAttribute('aria-disabled') === 'true') {
    return;
  }

  clearErrors(form);

  if (!checkRequiredChoices(form) || !form.reportValidity()) {
    return;
  }

  button.setAttribute('aria-disabled', 'true');

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
    });
    const body = await response.json().catch(() => ({}));

    if (response.ok) {
      const success = form.parentElement.querySelector('[data-forms-success]');
      const text = document.createElement('p');
      text.textContent = body.data?.message || form.dataset.formsThanks;
      success.replaceChildren(text);
      success.hidden = false;
      form.remove();
      success.focus();

      return;
    }

    showErrors(form, body);
  } catch {
    showErrors(form, {});
  } finally {
    button.removeAttribute('aria-disabled');
  }
}

// Checkboxes of which at least one must be chosen; the browser cannot check this.
function checkRequiredChoices(form) {
  for (const group of form.querySelectorAll('fieldset[data-required]')) {
    const boxes = group.querySelectorAll('input[type="checkbox"]');

    if (boxes.length > 0 && ![...boxes].some((box) => box.checked)) {
      boxes[0].setCustomValidity(form.dataset.formsChoose || ' ');
      boxes[0].addEventListener('change', () => boxes[0].setCustomValidity(''), { once: true });
    }
  }

  return true;
}

function clearErrors(form) {
  for (const error of form.querySelectorAll('[data-forms-error]')) {
    error.hidden = true;
    error.textContent = '';
  }

  for (const input of form.querySelectorAll('[aria-invalid]')) {
    input.removeAttribute('aria-invalid');
  }

  form.querySelector('[data-forms-alert]').hidden = true;
}

function showErrors(form, body) {
  const errors = body.errors ?? {};
  let first = null;

  for (const [pointer, message] of Object.entries(errors)) {
    const key = pointer.startsWith('/values/') ? pointer.slice('/values/'.length) : null;
    const field = key ? form.querySelector(`[data-forms-field="${CSS.escape(key)}"]`) : null;

    if (!field) {
      continue;
    }

    const error = field.querySelector('[data-forms-error]');
    error.textContent = message;
    error.hidden = false;

    for (const input of field.querySelectorAll('input, select, textarea')) {
      input.setAttribute('aria-invalid', 'true');
    }

    first ??= field.querySelector('input, select, textarea');
  }

  const alert = form.querySelector('[data-forms-alert]');
  const text = document.createElement('p');
  text.textContent = body.detail || body.title || form.dataset.formsFailed;
  alert.replaceChildren(text);
  alert.hidden = false;

  (first ?? alert).focus();
}

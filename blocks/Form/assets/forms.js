/*
 * SPDX-License-Identifier: MIT
 */

// Sends forms of the forms module with fetch (decision 0222): errors show
// at the fields, the thanks replaces the form. Without JavaScript the form
// is sent as usual and the page comes back with the result.

const forms = document.querySelectorAll('form[data-forms]');
const proofs = new WeakMap();

for (const form of forms) {
  refreshToken(form);
  proofs.set(form, startProof(form));
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
  const proof = proofs.get(form);

  try {
    // A captcha may need a moment before sending, e.g. for its token (decision 0262).
    const sending = { wait: [] };
    form.dispatchEvent(new CustomEvent('frootbox:forms-sending', { detail: sending }));
    await Promise.all([proof?.ready(), ...sending.wait]);
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

    failed(form, proof, body.errors);
    showErrors(form, body);
  } catch {
    failed(form, proof);
    showErrors(form, {});
  } finally {
    button.removeAttribute('aria-disabled');
  }
}

// After a failed try a captcha may start anew, e.g. the box of reCAPTCHA
// after an error at it; the errors are by pointer, e.g. "/captcha".
function failed(form, proof, errors = {}) {
  proof?.reset();
  form.dispatchEvent(new CustomEvent('frootbox:forms-failed', { detail: { errors } }));
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
    const key = pointer.startsWith('/values/') ? pointer.slice('/values/'.length) : (pointer === '/captcha' ? 'captcha' : null);
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

// The proof of work (decision 0261): a fresh task from the server, then
// the number whose SHA-256 with the salt gives its hash. It starts when
// the visitor first enters the form, so it is done before they send.
const PROOF_AGE = 50 * 60 * 1000;

function startProof(form) {
  const proof = form.querySelector('[data-forms-proof]');

  if (!proof) {
    return null;
  }

  const state = { running: null, solvedAt: 0 };
  const run = () => {
    state.running ??= solveProof(proof).then((solved) => {
      state.solvedAt = solved ? Date.now() : 0;
      state.running = null;
    });

    return state.running;
  };

  // The first sign of a person in the form: focus, a click or typing.
  const begin = () => {
    for (const type of ['focusin', 'pointerdown', 'input']) {
      form.removeEventListener(type, begin);
    }

    if (!state.solvedAt) {
      run();
    }
  };

  for (const type of ['focusin', 'pointerdown', 'input']) {
    form.addEventListener(type, begin);
  }

  return {
    // Before sending: solved and fresh, else solve now.
    async ready() {
      if (state.running) {
        await state.running;
      }

      if (!state.solvedAt || Date.now() - state.solvedAt > PROOF_AGE) {
        await run();
      }
    },
    // A task counts once, so after a failed try the next one is fresh.
    reset() {
      state.solvedAt = 0;
      proof.querySelector('[name="captcha[number]"]').value = '';
      run();
    },
  };
}

async function solveProof(proof) {
  try {
    const response = await fetch(proof.dataset.formsProof, { headers: { Accept: 'application/json' }, cache: 'no-store' });

    if (!response.ok) {
      return false;
    }

    const { data } = await response.json();

    let since = performance.now();

    for (let number = 0; number <= data.max; number++) {
      if (sha256(data.salt + number) === data.challenge) {
        proof.querySelector('[name="captcha[task]"]').value = data.task;
        proof.querySelector('[name="captcha[number]"]').value = String(number);

        return true;
      }

      // Room for the page every 40 ms; timers wait long in a tab in the background.
      if (number % 500 === 499 && performance.now() - since > 40) {
        await pause();
        since = performance.now();
      }
    }
  } catch {
    // Without a solution the entry goes to the spam folder, it is not lost.
  }

  return false;
}

function pause() {
  if (globalThis.scheduler?.yield) {
    return globalThis.scheduler.yield();
  }

  return new Promise((resolve) => {
    const channel = new MessageChannel();
    channel.port1.onmessage = () => resolve();
    channel.port2.postMessage(null);
  });
}

// SHA-256 of an ASCII text as hex, small and without crypto.subtle, which
// only works on HTTPS.
const K = new Uint32Array([
  0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
  0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
  0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
  0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
  0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
  0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
  0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
  0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
]);
const W = new Uint32Array(64);

function sha256(text) {
  const length = text.length;
  const blocks = (length + 9 + 63) >> 6;
  const words = new Uint32Array(blocks * 16);

  for (let i = 0; i < length; i++) {
    words[i >> 2] |= (text.charCodeAt(i) & 0xff) << (24 - (i & 3) * 8);
  }

  words[length >> 2] |= 0x80 << (24 - (length & 3) * 8);
  words[blocks * 16 - 1] = length * 8;

  let h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a;
  let h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;

  for (let block = 0; block < blocks; block++) {
    for (let t = 0; t < 16; t++) {
      W[t] = words[block * 16 + t];
    }

    for (let t = 16; t < 64; t++) {
      const a = W[t - 15];
      const b = W[t - 2];
      const s0 = ((a >>> 7) | (a << 25)) ^ ((a >>> 18) | (a << 14)) ^ (a >>> 3);
      const s1 = ((b >>> 17) | (b << 15)) ^ ((b >>> 19) | (b << 13)) ^ (b >>> 10);
      W[t] = W[t - 16] + s0 + W[t - 7] + s1;
    }

    let a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7;

    for (let t = 0; t < 64; t++) {
      const s1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
      const t1 = (h + s1 + ((e & f) ^ (~e & g)) + K[t] + W[t]) | 0;
      const s0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
      const t2 = (s0 + ((a & b) ^ (a & c) ^ (b & c))) | 0;
      h = g;
      g = f;
      f = e;
      e = (d + t1) | 0;
      d = c;
      c = b;
      b = a;
      a = (t1 + t2) | 0;
    }

    h0 = (h0 + a) | 0;
    h1 = (h1 + b) | 0;
    h2 = (h2 + c) | 0;
    h3 = (h3 + d) | 0;
    h4 = (h4 + e) | 0;
    h5 = (h5 + f) | 0;
    h6 = (h6 + g) | 0;
    h7 = (h7 + h) | 0;
  }

  return [h0, h1, h2, h3, h4, h5, h6, h7].map((word) => (word >>> 0).toString(16).padStart(8, '0')).join('');
}

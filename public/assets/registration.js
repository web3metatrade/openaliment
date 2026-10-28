"use strict";
(() => {
  const form = document.querySelector('#registration-form');
  if (!form) return;
  const cui = form.querySelector('[name="cui"]');
  const button = form.querySelector('[value="lookup"]');
  const progress = form.querySelector('#registration-progress');
  let sequence = 0;
  let pending;
  const result = () => form.querySelector('#anaf-result');
  cui.addEventListener('input', () => {
    sequence++;
    pending?.abort();
    result().hidden = true;
    const reference = form.querySelector('[name="organization_lookup_id"]');
    if (reference) reference.value = '';
    button.disabled = false;
    button.removeAttribute('aria-busy');
    progress.textContent = 'CUI modificat. Apasă OK pentru o nouă verificare.';
  });
  cui.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); form.requestSubmit(button); }
  });
  form.addEventListener('submit', async event => {
    if (event.submitter?.value !== 'lookup') return;
    event.preventDefault();
    const current = ++sequence;
    pending?.abort();
    pending = new AbortController();
    const retained = {};
    for (const key of ['display_name', 'email', 'password']) retained[key] = form.elements.namedItem(key)?.value || '';
    const body = new URLSearchParams({cui: cui.value, intent: 'lookup', _csrf: form.elements.namedItem('_csrf').value, display_name: retained.display_name, email: retained.email});
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    progress.textContent = 'Verificăm datele la ANAF…';
    try {
      const response = await fetch(form.action, {method: 'POST', body, credentials: 'same-origin', signal: pending.signal, headers: {Accept: 'text/html'}});
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (current !== sequence) return;
      const next = page.querySelector('#anaf-result');
      if (!next) throw new Error('unavailable');
      result().replaceWith(next);
      for (const key of ['display_name', 'email', 'password']) {
        const field = form.elements.namedItem(key);
        if (field) field.value = retained[key];
      }
      progress.textContent = response.ok ? 'Verificarea CUI-ului este încheiată.' : 'Verifică mesajul afișat și reîncearcă.';
      const heading = next.querySelector('h2');
      if (heading) { heading.tabIndex = -1; heading.focus({preventScroll: true}); }
    } catch (error) {
      if (current === sequence && error.name !== 'AbortError') progress.textContent = 'Verificarea nu a putut fi finalizată. Reîncearcă apăsând OK.';
    } finally {
      if (current === sequence) { button.disabled = false; button.removeAttribute('aria-busy'); }
    }
  });
})();

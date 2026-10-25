"use strict";
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', event => {
    if (!form.checkValidity()) return;
    const button = event.submitter || form.querySelector('button[type="submit"],button:not([type])');
    if (button) {
      if (button.name) { const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = button.name; hidden.value = button.value; form.append(hidden); }
      button.disabled = true; button.setAttribute('aria-busy', 'true');
    }
  });
});

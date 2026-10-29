"use strict";
const siteMenu = document.querySelector('[data-site-menu]');
if (siteMenu) {
  siteMenu.dataset.menuReady = '';
  const compact = matchMedia('(max-width: 1279px)');
  const resizeMenu = () => { siteMenu.open = !compact.matches; };
  resizeMenu();
  compact.addEventListener('change', resizeMenu);
  siteMenu.addEventListener('click', event => {
    if (compact.matches && event.target.closest('a')) siteMenu.open = false;
  });
  document.addEventListener('click', event => {
    if (compact.matches && !siteMenu.contains(event.target)) siteMenu.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && compact.matches && siteMenu.open) {
      siteMenu.open = false;
      siteMenu.querySelector('summary').focus();
    }
  });
}
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

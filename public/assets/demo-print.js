"use strict";
(() => {
  const button = document.querySelector('[data-print-demo]');
  if (button) { button.hidden = false; button.addEventListener('click', () => window.print()); }
})();

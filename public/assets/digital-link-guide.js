"use strict";
// Both examples remain available when JavaScript is disabled.
document.querySelectorAll('[data-dl-example]').forEach(example => {
  const controls = example.querySelector('.dl-example-controls');
  const buttons = Array.from(example.querySelectorAll('[data-dl-kind]'));
  const panels = Array.from(example.querySelectorAll('[data-dl-panel]'));
  const select = kind => {
    if (!panels.some(panel => panel.dataset.dlPanel === kind)) return;
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.dlKind === kind)));
    panels.forEach(panel => { panel.hidden = panel.dataset.dlPanel !== kind; });
  };
  buttons.forEach(button => button.addEventListener('click', () => select(button.dataset.dlKind)));
  select('product');
  controls.hidden = false;
});

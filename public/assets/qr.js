"use strict";
(() => {
  const target = document.querySelector('[data-qr]');
  if (!target || typeof qrcodegen === 'undefined') return;
  const qr = qrcodegen.QrCode.encodeText(target.dataset.qr, qrcodegen.QrCode.Ecc.MEDIUM);
  const quiet = 4, size = qr.size + quiet * 2, ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', `0 0 ${size} ${size}`); svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', 'Cod QR către pagina identificată'); svg.setAttribute('shape-rendering', 'crispEdges');
  const bg = document.createElementNS(ns, 'rect'); bg.setAttribute('width', '100%'); bg.setAttribute('height', '100%'); bg.setAttribute('fill', '#fff'); svg.append(bg);
  const path = document.createElementNS(ns, 'path'); const d = [];
  for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) if (qr.getModule(x, y)) d.push(`M${x + quiet},${y + quiet}h1v1h-1z`);
  path.setAttribute('d', d.join('')); path.setAttribute('fill', '#000'); svg.append(path); target.append(svg);
  const note = document.createElement('p'); note.className = 'small muted'; note.textContent = `${qr.size} × ${qr.size} module · margine liberă: 4 module`; target.append(note);
  document.querySelector('[data-download-qr]')?.addEventListener('click', () => {
    const copy = svg.cloneNode(true); copy.setAttribute('xmlns', ns); copy.removeAttribute('role'); copy.removeAttribute('aria-label');
    const blob = new Blob([new XMLSerializer().serializeToString(copy)], {type: 'image/svg+xml'});
    const href = URL.createObjectURL(blob), a = document.createElement('a'); a.href = href; a.download = 'openaliment-qr.svg'; a.click(); setTimeout(() => URL.revokeObjectURL(href), 1000);
  });
})();

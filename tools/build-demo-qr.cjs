/* Reproducible, static SVGs. Uses the same vendored encoder as producer QR exports. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'app/Domain/DemoBread.php'), 'utf8');
const gtin = source.match(/const GTIN14='([0-9]{14})'/)[1];
const lot = source.match(/const LOT='([^']+)'/)[1];
const context = {};
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/vendor/qrcodegen.js'), 'utf8'), context);
const directory = path.join(root, 'public/assets/demo');
fs.mkdirSync(directory, {recursive: true});
const result = [];
for (const batch of [false, true]) {
  const text = `https://openaliment.ro/01/${gtin}${batch ? '/10/' + encodeURIComponent(lot) : ''}`;
  const qr = context.qrcodegen.QrCode.encodeText(text, context.qrcodegen.QrCode.Ecc.MEDIUM);
  const total = qr.size + 8;
  const mm = Number((total * 0.6).toFixed(2));
  const modules = [];
  for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) if (qr.getModule(x, y)) modules.push(`M${x+4},${y+4}h1v1h-1z`);
  const filename = `pbt-${batch ? 'lot' : 'produs'}-qr.svg`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${mm}mm" height="${mm}mm" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges"><title>DEMO · Pâine Bună Tehnic · ${batch ? 'lot ' + lot : 'produs'}</title><desc>Identificator simulat 952. Fără vânzare. ${text}</desc><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="${modules.join('')}"/></svg>\n`;
  fs.writeFileSync(path.join(directory, filename), svg);
  result.push({filename, text, modules: qr.size, quiet_zone: 4, module_mm: 0.6, total_mm: mm});
}
console.log(JSON.stringify(result, null, 2));

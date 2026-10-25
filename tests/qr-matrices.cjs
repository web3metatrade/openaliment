const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const context = {};
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/vendor/qrcodegen.js'), 'utf8'), context);
const cases = [
  'https://openaliment.ro/01/09506000134352',
  'https://openaliment.ro/01/09506000134352/10/LotA',
  'https://openaliment.ro/01/09506000134352/10/A%2FB%2B00%25%3F',
  'https://openaliment.ro/01/09506000134352/10/A%252FB',
  'https://openaliment.ro/b/00000123456789abcdefabcdef0000000',
  'https://openaliment.ro/01/09506000134352/10/00000000000000000001'
];
const output = cases.map(text => {
  const qr = context.qrcodegen.QrCode.encodeText(text, context.qrcodegen.QrCode.Ecc.MEDIUM);
  return {text, size: qr.size, modules: Array.from({length:qr.size}, (_, y) => Array.from({length:qr.size}, (_, x) => qr.getModule(x, y)))};
});
fs.writeFileSync(path.join(__dirname, 'runtime/qr-matrices.json'), JSON.stringify(output));
console.log(`Generated ${output.length} QR matrices from the vendored library.`);

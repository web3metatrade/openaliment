/* Run after saving the image_gen final source; no image generation API dependency. */
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const source = process.argv[2] || 'output/imagegen/pbt-checkout-source.png';
const basename = process.argv[3] || 'pbt-checkout';
if (!/^[a-z0-9-]+$/.test(basename)) throw new Error('Invalid asset basename');
(async () => {
  for (const width of [768,1200]) {
    const target = path.join(root, `public/assets/${basename}-${width}.jpg`);
    await sharp(path.resolve(root, source)).resize({width})
      .jpeg({quality:85,mozjpeg:true,chromaSubsampling:'4:4:4'}).toFile(target);
    console.log(path.relative(root,target), fs.statSync(target).size);
  }
})();

'use strict';
const CACHE='openaliment-home-8';
const SHELL='/acasa/';
const MEDIA_CACHE='openaliment-home-media-1';
const MEDIA=['/assets/vendor/zxing-browser.min.js','/assets/ocr/tesseract.min.js','/assets/ocr/worker.min.js','/assets/ocr/tesseract-core-lstm.js','/assets/ocr/tesseract-core-lstm.wasm','/assets/ocr/lang/eng.traineddata.gz'];
const ASSETS=['/assets/home/home.css?v=8','/assets/home/app.js?v=8','/assets/home/domain.js?v=8','/assets/home/store.js?v=8','/assets/home/scanner.js?v=8','/assets/home/transfer.js?v=8','/assets/home/ocr.js?v=8','/assets/home/receipt.js?v=8','/assets/home/cloud.js?v=8','/assets/home/cloud-domain.js?v=8','/assets/home/privacy.js?v=8','/assets/mark.svg'];
self.addEventListener('install',event=>event.waitUntil((async()=>{const c=await caches.open(CACHE);await c.addAll(ASSETS);const r=await fetch(SHELL,{cache:'no-store'});if(r.ok&&r.headers.get('X-OpenAliment-App')==='home')await c.put(SHELL,r);})()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{const media=await caches.open(MEDIA_CACHE);for(const k of await caches.keys())if(k.startsWith('openaliment-home-')&&k!==CACHE&&k!==MEDIA_CACHE){const old=await caches.open(k);for(const path of MEDIA){if(await media.match(path))continue;const hit=await old.match(path);if(hit)await media.put(path,hit);}await caches.delete(k);}await self.clients.claim();})()));
self.addEventListener('fetch',event=>{
  const u=new URL(event.request.url);
  if(event.request.method!=='GET'||u.origin!==self.location.origin)return;
  // Public safety/food APIs, account routes and product pages are deliberately never cached here.
  if(u.pathname==='/acasa/'&&event.request.mode==='navigate'){
    event.respondWith((async()=>{try{const r=await fetch(event.request);if(r.ok&&r.headers.get('X-OpenAliment-App')==='home'){const c=await caches.open(CACHE);await c.put(SHELL,r.clone());}return r;}catch{const r=await caches.match(SHELL);return r||new Response('Deschide aplicația o dată când ai conexiune.',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8'}});}})());return;
  }
  const isMedia=MEDIA.includes(u.pathname),allowed=ASSETS.includes(u.pathname+u.search)||isMedia;
  if(allowed)event.respondWith((async()=>{const c=await caches.open(isMedia?MEDIA_CACHE:CACHE);const hit=await c.match(event.request);if(hit)return hit;const r=await fetch(event.request);if(r.ok)await c.put(event.request,r.clone());return r;})());
});

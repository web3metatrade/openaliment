/* Browser flows use isolated synthetic observations; no OFF network traffic. */
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),path=require('node:path');
const {chromium}=require(process.env.OA_NODE_MODULES?process.env.OA_NODE_MODULES+'/playwright':'playwright');
const ROOT=path.resolve(__dirname,'..'),BASE=process.env.OA_TEST_BASE_URL,checks=[];
if(!['localhost','127.0.0.1','::1'].includes(new URL(BASE).hostname))throw Error('Loopback only');
(async()=>{
 const f=JSON.parse(await fs.readFile(ROOT+'/tests/runtime/external-fixtures.json','utf8'));
 const browser=await chromium.launch({...(process.env.OA_BROWSER_CHANNEL?{channel:process.env.OA_BROWSER_CHANNEL}:{}),headless:true});
 const context=await browser.newContext({viewport:{width:390,height:844},acceptDownloads:true}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));let csrf;
 const check=(name,ok)=>{assert.ok(ok,name);checks.push({name,passed:true});};
 const api=async(url,body)=>{const r=await context.request.post(BASE+url,{headers:{'X-CSRF-Token':csrf,'Content-Type':'application/json'},data:body});assert.ok(r.ok(),await r.text());return r.json();};
 const scan=async(p,code)=>{await p.getByRole('button',{name:'Adaugă un aliment',exact:true}).click();await p.getByRole('button',{name:'Introdu codul',exact:true}).click();await p.locator('#code-form input').fill(code);await p.getByRole('button',{name:'Adaugă alimentul',exact:true}).click();await p.locator('.food-card').waitFor();};
 try{
  csrf=(await(await context.request.get(BASE+'/api/v2/identity/session')).json()).csrf;
  csrf=(await api('/api/v2/identity/sessions',{email:f.email,password:f.password})).csrf;
  await api('/api/v2/admin/sources/openfoodfacts/state',{state:'public',license_policy_confirmed:true});
  await page.goto(BASE+'/acasa/');await page.locator('#inventory[aria-busy=false]').waitFor();await scan(page,f.code);
  check('Scan adds exactly one package',await page.locator('.food-card').count()===1);
  await page.locator('.food-main').click();await page.getByRole('heading',{name:'Open Food Facts',exact:true}).waitFor();
  check('Consumer detail keeps external attribution visible',(await page.locator('#detail-body').innerText()).includes('ODbL 1.0'));
  const copy=await page.evaluate(async()=>JSON.parse(await(await(await import('/assets/home/transfer.js?v=10')).exportCopy()).text()));
  check('Personal v2 export includes source licenses',copy.schema_version==='openaliment.home.transfer.v2'&&copy.data.external_records[0].licenses.database.id==='ODbL-1.0');
  const fresh=await browser.newContext(),freshPage=await fresh.newPage();await freshPage.goto(BASE+'/acasa/');await freshPage.locator('#inventory[aria-busy=false]').waitFor();
  await freshPage.locator('#import-file').setInputFiles({name:'copy.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(copy))});await freshPage.locator('.food-card').waitFor();await freshPage.locator('.food-main').click();
  check('Personal import restores licensed observation on a new device',(await freshPage.locator('#detail-body').innerText()).includes('Open Food Facts'));await fresh.close();
  const unknown=await(await context.request.get(BASE+'/api/v2/public/food?code='+f.code)).json(),cold=await browser.newContext(),coldPage=await cold.newPage();
  await cold.route('**/api/v2/public/food/resolve',route=>route.fulfill({contentType:'application/json',body:JSON.stringify({schema_version:'openaliment.consumer-resolution.v1',profile:unknown,supplement:null,external_state:'not_requested',policy_version:'openaliment.source-selection.v1'})}));
  await cold.route('**/api/v2/public/food/lookups',async route=>{await new Promise(r=>setTimeout(r,500));await route.fulfill({contentType:'application/json',body:JSON.stringify({state:'completed',result_code:'found',supplement:copy.data.external_records[0]})});});
  await coldPage.goto(BASE+'/acasa/');await coldPage.locator('#inventory[aria-busy=false]').waitFor();await scan(coldPage,f.code);
  await coldPage.waitForFunction(name=>document.querySelector('.food-name')?.textContent===name,copy.data.external_records[0].name);
  check('Cold lookup updates the visible package without another click',await coldPage.locator('.food-card').count()===1);
  await coldPage.locator('#undo').click();await coldPage.waitForFunction(()=>!document.querySelector('.food-card'));
  check('User can undo an asynchronously enriched addition',await coldPage.locator('.food-card').count()===0);await cold.close();
  check('Phone layout has no horizontal overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.screenshot({path:ROOT+'/tests/runtime/previews/off-phone-detail.png',fullPage:true});await page.getByRole('button',{name:'Închide detaliile',exact:true}).click();
  await page.evaluate(()=>navigator.serviceWorker.ready);await page.waitForFunction(()=>navigator.serviceWorker.controller!==null);
  await context.setOffline(true);await page.reload();await page.locator('.food-card').waitFor();check('Package survives reload offline',await page.locator('.food-card').count()===1);
  await page.locator('.food-main').click();check('External observation is readable offline',(await page.locator('#detail-body').innerText()).includes('Open Food Facts'));await context.setOffline(false);
  await page.goto(BASE+'/manage/items/'+f.item+'/sources');check('Producer source page renders comparison',(await page.locator('main').innerText()).includes('Open Food Facts'));
  await page.setViewportSize({width:1440,height:960});await page.screenshot({path:ROOT+'/tests/runtime/previews/off-producer-desktop.png',fullPage:true});
  check('Producer layout has no overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));check('No browser runtime errors',errors.length===0);
 }finally{try{await api('/api/v2/admin/sources/openfoodfacts/state',{state:'disabled'});}finally{await browser.close();}}
 await fs.writeFile(ROOT+'/tests/runtime/reports/external-browser.json',JSON.stringify({passed:checks.length,failed:0,checks}));console.log(JSON.stringify({passed:checks.length,failed:0}));
})().catch(e=>{console.error(e);process.exitCode=1;});

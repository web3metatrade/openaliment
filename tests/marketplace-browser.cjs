/* Isolated UI and actual QR encoding/decoding. No external website or printer is contacted. */
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),path=require('node:path');
const {chromium}=require(process.env.OA_NODE_MODULES?process.env.OA_NODE_MODULES+'/playwright':'playwright');
const ROOT=path.resolve(__dirname,'..'),BASE=process.env.OA_TEST_BASE_URL,checks=[];
if(!BASE||new URL(BASE).hostname!=='localhost')throw Error('Loopback browser tests only');
function check(name,value){assert.ok(value,name);checks.push({name,passed:true});}
(async()=>{
 const f=JSON.parse(await fs.readFile(path.join(ROOT,'tests/runtime/marketplace-fixtures.json'),'utf8'));
 const d=JSON.parse(await fs.readFile(path.join(ROOT,'tests/runtime/marketplace-browser-fixtures.json'),'utf8'));
 const browser=await chromium.launch({...(process.env.OA_BROWSER_CHANNEL?{channel:process.env.OA_BROWSER_CHANNEL}:{}),headless:true});
 const context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
 await context.route('**/*',route=>{const u=new URL(route.request().url());return ['localhost','127.0.0.1'].includes(u.hostname)||['data:','blob:'].includes(u.protocol)?route.continue():route.abort();});
 try{
  await page.goto(BASE+'/login');await page.locator('[name=email]').fill(f.email);await page.locator('[name=password]').fill(f.password);await page.getByRole('button',{name:'Intră în cont',exact:true}).click();await page.waitForURL(BASE+'/manage');
  const pages=[['/marketplace','marketplace'],['/marketplace/'+d.slug,'marketplace-detail'],['/developers/marketplace/'+d.app_id,'marketplace-editor'],['/admin/marketplace','marketplace-review'],['/manage/printing?job='+d.job_id,'printing'],['/standarde/gs1','gs1-standards']];
  for(const viewport of [{width:1440,height:960},{width:390,height:844}]){
   await page.setViewportSize(viewport);
   for(const [url,name]of pages){
    const response=await page.goto(BASE+url);check(name+' HTTP '+viewport.width,response.status()===200);
    check(name+' fits viewport '+viewport.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    check(name+' has one primary heading '+viewport.width,await page.locator('h1').count()===1);
    await page.screenshot({path:path.join(ROOT,'tests/runtime/previews',name+'-'+viewport.width+'.png'),fullPage:true});
   }
  }
  await page.goto(BASE+'/manage/printing?job='+d.job_id);await page.locator('[data-qr] svg').waitFor();
  check('Serialized QR preview has a four-module quiet zone',await page.locator('[data-qr]').innerText().then(text=>text.includes('4 module')));
  await page.addScriptTag({url:BASE+'/assets/vendor/zxing-browser.min.js'});
  const decoded=await page.evaluate(async()=>{const svg=document.querySelector('[data-qr] svg').cloneNode(true);svg.setAttribute('xmlns','http://www.w3.org/2000/svg');svg.removeAttribute('aria-label');svg.removeAttribute('role');svg.setAttribute('width','800');svg.setAttribute('height','800');const image='data:image/svg+xml;base64,'+btoa(new XMLSerializer().serializeToString(svg));return (await new ZXingBrowser.BrowserQRCodeReader().decodeFromImageUrl(image)).getText();});
  check('Actual QR decodes to exact GS1 URI with production dates',decoded===d.digital_link&&decoded.includes('/21/')&&decoded.includes('11=261025')&&decoded.includes('15=261030'));
  check('No browser script errors',errors.length===0);
  await fs.writeFile(path.join(ROOT,'tests/runtime/reports/marketplace-browser.json'),JSON.stringify({passed:checks.length,failed:0,checks,physical_printer_or_pos:false},null,2));console.log(JSON.stringify({passed:checks.length,failed:0}));
 }catch(error){await page.screenshot({path:path.join(ROOT,'tests/runtime/previews/marketplace-failure.png'),fullPage:true}).catch(()=>{});throw error;}finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});

/* Registration UI tests use only the disposable local ANAF fixture transport. */
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),path=require('node:path'),crypto=require('node:crypto');
const {chromium}=require(process.env.OA_NODE_MODULES?process.env.OA_NODE_MODULES+'/playwright':'playwright');
const ROOT=path.resolve(__dirname,'..'),BASE=process.env.OA_TEST_BASE_URL,checks=[];
if(!['localhost','127.0.0.1','::1'].includes(new URL(BASE).hostname))throw Error('Loopback only');
(async()=>{
 const browser=await chromium.launch({...(process.env.OA_BROWSER_CHANNEL?{channel:process.env.OA_BROWSER_CHANNEL}:{}),headless:true});
 const context=await browser.newContext({viewport:{width:390,height:844}}),page=await context.newPage(),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 const check=(name,ok)=>{assert.ok(ok,name);checks.push({name,passed:true});};
 const lookup=async(code,name)=>{await page.getByLabel('CUI',{exact:true}).fill(code);await page.getByRole('button',{name:'Verifică CUI-ul la ANAF',exact:true}).click();await page.getByRole('heading',{name,exact:true}).waitFor();};
 try{
  await page.goto(BASE+'/register');
  check('First visit asks only for the CUI',await page.getByLabel('CUI',{exact:true}).count()===1&&await page.locator('[name=email]').count()===0);
  await page.getByLabel('CUI',{exact:true}).fill('abc');await page.getByRole('button',{name:'Verifică CUI-ul la ANAF',exact:true}).click();await page.getByRole('alert').waitFor();
  check('Invalid CUI leaves the field editable',await page.getByLabel('CUI',{exact:true}).inputValue()==='abc');
  await lookup('RO 900000001','Organizație sintetică ANAF A');
  check('All 58 source fields are visible without expanding accordions',await page.locator('#anaf-result .anaf-field').count()===58&&await page.locator('#anaf-result .anaf-data-group').count()===7&&await page.locator('#anaf-result details').count()===0);
  await page.getByLabel('CUI',{exact:true}).fill('900000002');
  check('Editing CUI immediately invalidates the previous result',await page.locator('#anaf-result').isHidden()&&await page.locator('[name=organization_lookup_id]').inputValue()==='');
  await page.getByLabel('CUI',{exact:true}).fill('1234');await page.getByRole('button',{name:'Verifică CUI-ul la ANAF',exact:true}).click();await page.getByText('Nu am găsit acest CUI în ANAF. Verifică cifrele și încearcă din nou.',{exact:true}).waitFor();
  check('Not-found offers a correction rather than an empty account',await page.locator('[name=email]').count()===0);
  await lookup('900000002','Organizație sintetică ANAF B');
  check('Phone registration has no horizontal overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.screenshot({path:ROOT+'/tests/runtime/previews/anaf-registration-phone.png',fullPage:true});
  await page.setViewportSize({width:1440,height:960});await page.screenshot({path:ROOT+'/tests/runtime/previews/anaf-registration-desktop.png',fullPage:true});
  const password='Synthetic-'+crypto.randomBytes(18).toString('hex');
  await page.getByLabel('Numele tău',{exact:true}).fill('Persoană sintetică ANAF');await page.getByLabel('Email de contact',{exact:true}).fill('anaf-browser-'+crypto.randomBytes(12).toString('hex')+'@example.invalid');await page.getByLabel('Parolă',{exact:true}).fill(password);
  await page.getByRole('button',{name:/Creează contul pentru această firmă/}).click();await page.waitForURL('**/manage/organization');
  check('Creating the account lands on the saved organization profile',(await page.locator('main').innerText()).includes('Organizație sintetică ANAF B'));
  await page.reload();
  check('Profile survives a new request and a new page load',(await page.locator('main').innerText()).includes('001234'));
  const saved=await(await context.request.get(BASE+'/api/v2/manage/organization/legal-profile')).json();
  check('API reads exactly the confirmed corrected CUI',saved.profile.cui==='900000002'&&saved.profile.record.date_generale.denumire==='Organizație sintetică ANAF B'&&saved.profile.directly_verified===true);
  const nojs=await browser.newContext({javaScriptEnabled:false}),plain=await nojs.newPage();
  await plain.goto(BASE+'/register');await plain.getByLabel('CUI',{exact:true}).fill('900000001');await plain.getByRole('button',{name:'Verifică CUI-ul la ANAF',exact:true}).click();await plain.getByRole('heading',{name:'Organizație sintetică ANAF A',exact:true}).waitFor();
  check('Registration lookup also works without JavaScript',await plain.locator('.anaf-field').count()===58);
  await plain.getByLabel('CUI',{exact:true}).fill('900000002');await plain.getByLabel('Numele tău',{exact:true}).fill('Persoană sintetică fără JS');await plain.getByLabel('Email de contact',{exact:true}).fill('anaf-nojs-'+crypto.randomBytes(12).toString('hex')+'@example.invalid');await plain.getByLabel('Parolă',{exact:true}).fill(password);await plain.getByRole('button',{name:/Creează contul pentru această firmă/}).click();await plain.getByRole('alert').waitFor();
  check('Without JavaScript, a changed unconfirmed CUI cannot create the old firm',new URL(plain.url()).pathname==='/register'&&(await plain.locator('main').innerText()).includes('CUI-ul confirmat nu corespunde'));
  await nojs.close();check('Registration produces no browser JavaScript errors',errors.length===0);
  await fs.writeFile(ROOT+'/tests/runtime/reports/anaf-browser.json',JSON.stringify({passed:checks.length,failed:0,checks},null,2));console.log(JSON.stringify({passed:checks.length,failed:0}));
 }finally{await context.close();await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

/* Real navigator.credentials with a virtual authenticator; isolated account only. */
const assert=require('node:assert/strict'),fs=require('node:fs/promises'),path=require('node:path');
const {chromium}=require(process.env.OA_NODE_MODULES?process.env.OA_NODE_MODULES+'/playwright':'playwright');
const BASE=process.env.OA_TEST_BASE_URL,ROOT=path.resolve(__dirname,'..'),checks=[];
if(!BASE||new URL(BASE).hostname!=='localhost')throw Error('WebAuthn browser test requires isolated localhost.');
function check(name,value){assert.ok(value,name);checks.push({name,passed:true});console.log('PASS '+name);}
(async()=>{
 const browser=await chromium.launch({...(process.env.OA_BROWSER_CHANNEL?{channel:process.env.OA_BROWSER_CHANNEL}:{}),headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:960}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  const cdp=await context.newCDPSession(page);await cdp.send('WebAuthn.enable');await cdp.send('WebAuthn.addVirtualAuthenticator',{options:{protocol:'ctap2',transport:'internal',hasResidentKey:true,hasUserVerification:true,isUserVerified:true,automaticPresenceSimulation:true}});
  const email='browser-'+Date.now()+'@example.invalid',password='Synthetic-browser-'+Date.now()+'-account';
  await page.goto(BASE+'/register');await page.locator('[name=display_name]').fill('Browser test');await page.locator('[name=organization]').fill('Browser test organization');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(password);await page.getByRole('button',{name:'Creează contul'}).click();await page.waitForURL(BASE+'/manage');
  await page.goto(BASE+'/manage/security');await page.locator('[data-passkey-create] [name=name]').fill('Telefon de probă');await page.locator('[data-passkey-create] [name=password]').fill(password);await page.locator('[data-passkey-create] button').click();await page.locator('[data-recovery-codes]:not([hidden])').waitFor();
  check('Actual WebAuthn browser registration displays recovery codes once',(await page.locator('.recovery-codes').innerText()).trim().split('\n').length===10);
  check('Security page has no desktop overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.getByRole('link',{name:'Am păstrat codurile'}).click();check('Reload does not redisplay recovery secrets',await page.locator('[data-recovery-codes]').isHidden());
  await page.setViewportSize({width:390,height:844});await page.waitForFunction(()=>!document.querySelector('[data-site-menu]').open);check('Security page has no mobile overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:path.join(ROOT,'tests/runtime/previews/account-security-mobile.png'),fullPage:true});
  check('Session cookie is HttpOnly',(await context.cookies()).find(c=>c.name==='oa_session').httpOnly);check('Account secrets are not written to localStorage',await page.evaluate(()=>localStorage.length===0));
  await page.goto(BASE+'/manage');await page.locator('.workspace-account summary').click();await page.getByRole('button',{name:'Ieși din cont',exact:true}).click();await page.waitForURL(BASE+'/');
  await page.goto(BASE+'/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(password);await page.getByRole('button',{name:'Intră în cont'}).click();await page.waitForURL(BASE+'/account/verify');check('Password login asks for the enrolled second factor',await page.getByRole('heading',{name:'Confirmă accesul'}).isVisible());
  await page.getByRole('button',{name:'Continuă cu cheia de acces'}).click();await page.waitForURL(BASE+'/manage');check('Browser signed assertion opens the producer workspace',await page.getByRole('heading',{name:'Produse și loturi',exact:true}).isVisible()&&await page.getByText('Browser test organization',{exact:true}).isVisible());
  check('No browser exceptions in the account flow',errors.length===0);
  await fs.writeFile(path.join(ROOT,'tests/runtime/reports/account-access-browser.json'),JSON.stringify({passed:checks.length,failed:0,checks,environment:'Local Edge virtual WebAuthn authenticator; no physical device claim'},null,2));console.log(JSON.stringify({passed:checks.length,failed:0}));
 }catch(error){await page.screenshot({path:path.join(ROOT,'tests/runtime/previews/account-access-failure.png'),fullPage:true}).catch(()=>{});throw error;}finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});

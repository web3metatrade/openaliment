/* First-use regressions on disposable local data. No real camera, POS or email. */
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const path = require('node:path');
const {chromium} = require(process.env.OA_NODE_MODULES ? process.env.OA_NODE_MODULES + '/playwright' : 'playwright');
const ROOT = path.resolve(__dirname, '..');
const BASE = process.env.OA_TEST_BASE_URL;
if (!BASE || new URL(BASE).hostname !== 'localhost') throw Error('Disposable localhost only');
const checks = [];
function check(name, value) {
  assert.ok(value, name);
  checks.push({name, passed: true});
  console.log('PASS ' + name);
}

(async () => {
  const browser = await chromium.launch({...(process.env.OA_BROWSER_CHANNEL ? {channel: process.env.OA_BROWSER_CHANNEL} : {}), headless: true});
  const context = await browser.newContext({viewport: {width: 390, height: 844}});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await context.route('**/*', route => {
    const url = new URL(route.request().url());
    return ['localhost', '127.0.0.1'].includes(url.hostname) || ['data:', 'blob:'].includes(url.protocol) ? route.continue() : route.abort();
  });
  try {
    for (const viewport of [{width: 320, height: 568}, {width: 390, height: 844}, {width: 768, height: 1024}, {width: 1440, height: 900}]) {
      await page.setViewportSize(viewport);
      await page.goto(BASE + '/');
      const home = page.locator('.shell-header .nav-home');
      check('Pantry is visible in the header at ' + viewport.width, await home.isVisible() && (await home.boundingBox()).y < viewport.height);
      check('Homepage fits viewport at ' + viewport.width, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      if (viewport.width === 390) {
        const cta = page.locator('.hero-actions .btn-primary');
        const bounds = await cta.boundingBox();
        check('Mobile example CTA is fully above the fold', bounds.y >= 0 && bounds.y + bounds.height <= viewport.height);
        check('Mobile CTA precedes the photo', bounds.y < (await page.locator('.hero-checkout').boundingBox()).y);
        check('Consumer entry needs no menu expansion', !(await page.locator('[data-site-menu]').evaluate(element => element.open)));
        await page.locator('[data-site-menu] summary').click();
        check('Compact menu opens developer navigation', await page.getByRole('link', {name: 'Dezvoltatori', exact: true}).isVisible());
        await page.keyboard.press('Escape');
        check('Escape closes menu and restores focus', await page.locator('[data-site-menu] summary').evaluate(element => document.activeElement === element && !element.parentElement.open));
        await page.screenshot({path: path.join(ROOT, 'tests/runtime/previews/first-visit-home-390.png'), fullPage: false});
      }
    }
    await page.setViewportSize({width: 390, height: 844});
    await page.goto(BASE + '/');
    await page.locator('.hero-actions .btn-primary').click();
    await page.waitForURL(BASE + '/demo/paine-buna-tehnic/lot/DEMO-PBT-01');
    check('Example CTA resolves to the precise demonstration lot', await page.getByRole('heading', {name: 'Ce știm despre acest aliment'}).isVisible());
    check('Demo keeps technical QR details optional', !(await page.locator('.demo-technical').evaluate(element => element.open)) && (await page.locator('.demo-food-summary').innerText()).includes('Nu există o declarație'));
    await page.getByRole('link', {name: 'Adaugă acasă', exact: true}).click();
    await page.locator('.food-card').waitFor();
    check('Guest adds demonstration without an account gate', (await page.locator('.food-card').innerText()).includes('Demonstrație') && await page.locator('input[type=password]').count() === 0);
    check('Allergen preferences have a visible text label', await page.getByRole('button', {name: 'Preferințe și datele mele'}).innerText().then(text => text.includes('Preferințe')));
    await page.getByRole('button', {name: 'Adaugă', exact: true}).click();
    await page.getByRole('button', {name: 'Introdu codul', exact: true}).click();
    await page.locator('#code-form input').fill('123');
    await page.getByRole('button', {name: 'Adaugă alimentul', exact: true}).click();
    await page.locator('#code-error:not([hidden])').waitFor();
    check('Invalid code keeps the dialog open', await page.locator('#code-dialog').evaluate(element => element.open));
    check('Invalid code preserves value and focus', await page.locator('#code-form input').evaluate(element => element.value === '123' && document.activeElement === element && element.getAttribute('aria-invalid') === 'true'));
    check('Invalid code creates no package', await page.locator('.food-card').count() === 1);
    await page.locator('#code-form input').fill('9520000000011');
    await page.getByRole('button', {name: 'Adaugă alimentul', exact: true}).click();
    await page.locator('#code-dialog').waitFor({state: 'hidden'});
    check('Correction succeeds without reopening the dialog', await page.locator('.food-card').count() === 2);
    await page.locator('#undo').click();
    await page.locator('.food-card').nth(1).waitFor({state: 'detached'});
    await page.locator('.food-main').click();
    await page.getByRole('button', {name: 'Corectează alimentul', exact: true}).click();
    await page.locator('#edit-form [name=name]').fill('Pâine pentru proba UX');
    await page.locator('#edit-form [name=quantity]').fill('2');
    await page.getByRole('button', {name: 'Salvează', exact: true}).click();
    await page.locator('#editor').waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'Adaugă data de pe ambalaj', exact: true}).click();
    check('Date action opens a focused date editor', await page.getByRole('heading', {name: 'Data de pe ambalaj', exact: true}).isVisible() && await page.locator('#edit-form [name=name]').isHidden() && await page.locator('#edit-form [name=quantity]').isHidden());
    check('Unusual date precision remains optional', !(await page.locator('#date-format').evaluate(element => element.open)));
    await page.getByRole('button', {name: 'Salvează', exact: true}).click();
    await page.locator('#edit-error:not([hidden])').waitFor();
    check('Blank date does not claim a successful save', await page.locator('#editor').evaluate(element => element.open));
    await page.locator('#edit-form [name=date_value]').fill('2026-11-07');
    await page.getByRole('button', {name: 'Salvează', exact: true}).click();
    await page.locator('#edit-error:not([hidden])').waitFor();
    check('Date type is never guessed', await page.locator('#editor').evaluate(element => element.open) && await page.locator('#edit-form [name=date_kind]').inputValue() === 'unknown');
    await page.locator('#edit-form [name=date_kind]').selectOption('best_before');
    await page.getByRole('button', {name: 'Salvează', exact: true}).click();
    await page.locator('#editor').waitFor({state: 'hidden'});
    check('Date-only save preserves name and quantity', await page.locator('.food-card').innerText().then(text => text.includes('Pâine pentru proba UX') && text.includes('2 ambalaje') && text.includes('07.11.2026')));
    await page.reload();
    await page.locator('.food-card').waitFor();
    check('Reload preserves the focused date change', (await page.locator('.food-card').innerText()).includes('07.11.2026'));
    await page.screenshot({path: path.join(ROOT, 'tests/runtime/previews/first-visit-pantry-390.png'), fullPage: false});
    const upgradeContext = await browser.newContext({viewport: {width: 390, height: 844}});
    const upgradePage = await upgradeContext.newPage();
    await upgradePage.goto(BASE + '/acasa/');
    await upgradePage.waitForFunction(() => navigator.serviceWorker.controller?.state === 'activated');
    await upgradePage.getByRole('button', {name: 'Adaugă un aliment', exact: true}).click();
    await upgradePage.getByRole('button', {name: 'Introdu codul', exact: true}).click();
    await upgradePage.locator('#code-form input').fill('9520000000011');
    await upgradePage.getByRole('button', {name: 'Adaugă alimentul', exact: true}).click();
    await upgradePage.locator('#code-dialog').waitFor({state: 'hidden'});
    // A real test-server response avoids Playwright's updated-worker routing limitation.
    // This models the cache lifecycle, not every byte of the historical application.
    await upgradePage.evaluate(() => navigator.serviceWorker.register('/acasa/legacy-test-worker.js', {scope: '/acasa/'}));
    await upgradePage.waitForFunction(() => navigator.serviceWorker.controller?.scriptURL.endsWith('/legacy-test-worker.js') && navigator.serviceWorker.controller.state === 'activated');
    await upgradePage.evaluate(() => caches.delete('openaliment-home-9'));
    check('Legacy cache fixture is active before update', await upgradePage.evaluate(async () => (await caches.keys()).includes('openaliment-home-8') && !(await caches.keys()).includes('openaliment-home-9')));
    await upgradePage.evaluate(() => navigator.serviceWorker.register('/acasa/sw.js', {scope: '/acasa/'}));
    await upgradePage.waitForFunction(() => navigator.serviceWorker.controller?.scriptURL.endsWith('/acasa/sw.js') && navigator.serviceWorker.controller.state === 'activated');
    check('PWA upgrade replaces simulated legacy worker without closing the page', await upgradePage.evaluate(async () => (await caches.keys()).includes('openaliment-home-9') && !(await caches.keys()).includes('openaliment-home-8')));
    check('Updated PWA shell is cached before offline use', await upgradePage.evaluate(async () => {
      const shell = await (await caches.open('openaliment-home-9')).match('/acasa/');
      return shell?.ok && shell.headers.get('X-OpenAliment-App') === 'home';
    }));
    await upgradeContext.setOffline(true);
    await upgradePage.reload();
    await upgradePage.locator('.food-card').waitFor();
    check('Upgraded PWA preserves inventory and opens offline', await upgradePage.locator('.food-card').innerText().then(text => text.includes('Pâine Bună Tehnic')) && (await upgradePage.locator('#connection').innerText()).includes('Fără conexiune'));
    await upgradeContext.close();
    await page.goto(BASE + '/api/docs');
    await page.locator('.api-sidebar>summary').click();
    await page.locator('#api-search').fill('Creează un lot');
    const best = page.locator('#api-search-results li a').first();
    check('API search ranks v2 batch creation first', (await best.getAttribute('href')) === '/api/docs/reference/manageBatchCreate');
    check('Search shows the purpose and API version without hover', await best.innerText().then(text => text.includes('Creează un lot') && text.includes('v2')));
    await best.click();
    check('Selecting a result closes mobile API navigation', !(await page.locator('.api-sidebar').evaluate(element => element.open)));
    await page.goto(BASE + '/api/docs');
    await page.locator('.api-sidebar>summary').click();
    await page.locator('#api-search').fill('NoMatchingSyntheticAction98372');
    check('API search has an explicit empty result', (await page.locator('#api-search-status').innerText()).includes('Nu am găsit rezultate'));
    await page.locator('#api-search').fill('');
    check('Clearing search restores guide navigation', await page.getByRole('link', {name: 'Primul produs și lot', exact: true}).isVisible());
    await page.goto(BASE + '/marketplace?q=NoMatchingSyntheticIntegration98372');
    check('Marketplace empty search offers recovery', await page.getByRole('link', {name: 'Resetează căutarea', exact: true}).isVisible() && await page.getByRole('heading', {name: 'Nu am găsit o integrare pentru această căutare.'}).isVisible());
    const email = 'ux-browser-' + Date.now() + '@example.invalid';
    await page.goto(BASE + '/register');
    await page.locator('[name=display_name]').fill('UX test');
    await page.locator('[name=organization]').fill('UX test organization');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill('Synthetic-UX-' + Date.now() + '-only');
    await page.getByRole('button', {name: 'Creează contul', exact: true}).click();
    await page.waitForURL(BASE + '/manage');
    const create = page.getByRole('button', {name: 'Creează produsul', exact: true});
    const position = await create.boundingBox();
    check('Empty producer account prioritizes first product', await page.getByRole('heading', {name: 'Adaugă primul aliment'}).isVisible() && await page.locator('.stats-grid').count() === 0);
    check('First-product submit is visible on mobile', position.y >= 0 && position.y + position.height <= 844);
    await page.locator('[name=name]').fill('Iaurt UX de probă');
    await page.locator('[name=category]').fill('Lactate');
    await create.click();
    await page.waitForURL(/\/manage\/products\/[a-f0-9]{32}$/);
    check('Reorganized producer form still creates a non-bread product', await page.getByRole('heading', {name: 'Iaurt UX de probă', exact: true}).isVisible());
    const noScriptContext = await browser.newContext({viewport: {width: 390, height: 844}, javaScriptEnabled: false});
    const noScriptPage = await noScriptContext.newPage();
    await noScriptPage.goto(BASE + '/demo/paine-buna-tehnic/lot/DEMO-PBT-01');
    check('QR information is readable without JavaScript', await noScriptPage.getByRole('heading', {name: 'Ce știm despre acest aliment'}).isVisible() && await noScriptPage.locator('.demo-food-summary').innerText().then(text => text.includes('Ingrediente și alergeni')));
    check('Without JavaScript menu remains collapsed and usable', !(await noScriptPage.locator('[data-site-menu]').evaluate(element => element.open)) && await noScriptPage.locator('[data-site-menu]>summary').isVisible());
    await noScriptPage.locator('[data-site-menu]>summary').click();
    check('Native menu opens without JavaScript', await noScriptPage.getByRole('link', {name: 'Alimente', exact: true}).isVisible());
    await noScriptContext.close();
    check('No unhandled browser errors', errors.length === 0);
    await fs.writeFile(path.join(ROOT, 'tests/runtime/reports/first-visit-browser.json'), JSON.stringify({executed_at: new Date().toISOString(), passed: checks.length, failed: 0, checks, environment: 'Isolated Chromium responsive simulation; no physical device or POS claim'}, null, 2));
    console.log(JSON.stringify({passed: checks.length, failed: 0}));
  } catch (error) {
    await page.screenshot({path: path.join(ROOT, 'tests/runtime/previews/first-visit-failure.png'), fullPage: false}).catch(() => {});
    throw error;
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error); process.exit(1);});

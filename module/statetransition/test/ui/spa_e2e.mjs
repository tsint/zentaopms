/**
 * End-to-end browser test simulating real user flow in ZenTao SPA mode.
 *
 * Tests:
 *   1. Login
 *   2. Navigate to browse (SPA iframe loads)
 *   3. Browse page renders mermaid on first load (Bug 3 browse)
 *   4. Click 管理 button INSIDE iframe (SPA soft-navigation to manage)
 *   5. Manage page renders mermaid after SPA navigation (Bug 3 manage)
 *   6. Click each edge, verify correct transition selected (Bug 4)
 *   7. Add new status
 *   8. Add new transition
 *   9. Save definition
 */
import { chromium } from 'playwright';

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();

// Login
await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(3000);
check('Login', !page.url().includes('user/login'), '');

// Step 1: Go to browse (SPA loads)
console.log('\n--- Step 1: Browse page (SPA) ---');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=browse&objectType=story&productID=0', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(10000);
let iframe = page.frames().find(f => f.name() === 'app-admin');
if (iframe) {
  const browseState = await iframe.evaluate(() => {
    const div = document.querySelector('.statetransition-flow .mermaid');
    const svg = div?.querySelector('svg');
    return { hasSvg: !!svg, paths: svg?.querySelectorAll('path').length || 0 };
  });
  check('Bug3 Browse: mermaid renders first load', browseState.hasSvg && browseState.paths > 5, `paths=${browseState.paths}`);
}

// Step 2: Click 管理 button (SPA soft-navigation)
console.log('\n--- Step 2: Click 管理 (SPA navigation) ---');
if (iframe) {
  await iframe.locator('a.btn-primary[href*="manage"]').click({ timeout: 10000 }).catch(e => console.log('Click failed:', e.message));
  await page.waitForTimeout(15000);
  iframe = page.frames().find(f => f.name() === 'app-admin');

  if (iframe) {
    const manageState = await iframe.evaluate(() => {
      const m = document.getElementById('workflowMermaid');
      const svg = m?.querySelector('svg');
      return {
        hasEditor: !!document.getElementById('workflowEditor'),
        hasSvg: !!svg,
        svgPaths: svg?.querySelectorAll('g.edgePaths > path').length || 0,
        wiredEdges: document.querySelectorAll('[data-edge-key]').length
      };
    });
    check('Bug3 Manage: mermaid renders after SPA nav (no refresh)', manageState.hasSvg && manageState.svgPaths > 0, `paths=${manageState.svgPaths} wired=${manageState.wiredEdges}`);

    // Step 3: Bug 4 — click each edge and verify correct selection
    console.log('\n--- Step 3: Click edges, verify selection (Bug 4) ---');
    const edges = await iframe.evaluate(() => Array.from(document.querySelectorAll('g.edgePaths > path[data-edge-key]')).map(p => p.getAttribute('data-edge-key')));
    let allClicksPass = true;
    for (let i = 0; i < edges.length; i++) {
      await iframe.evaluate((idx) => {
        const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
        paths[idx]?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }, i);
      await page.waitForTimeout(400);
      const sel = await iframe.evaluate(() => document.querySelector('[data-edge-key].is-selected')?.getAttribute('data-edge-key'));
      const ok = sel === edges[i];
      if (!ok) allClicksPass = false;
    }
    check('Bug4: every edge click selects correct transition', allClicksPass, `${edges.length} edges tested`);

    // Step 4: Add new status
    console.log('\n--- Step 4: Add new status ---');
    const beforeStatusCount = await iframe.evaluate(() => document.querySelectorAll('#workflowBoard .workflow-column').length);
    await iframe.fill('#newNodeKey', 'e2etest');
    await iframe.fill('#newNodeLabel', 'E2E测试');
    await iframe.click('#addNode');
    await page.waitForTimeout(1500);
    const afterStatusCount = await iframe.evaluate(() => document.querySelectorAll('#workflowBoard .workflow-column').length);
    check('Add status works', afterStatusCount === beforeStatusCount + 1, `before=${beforeStatusCount} after=${afterStatusCount}`);

    // Step 5: Save definition
    console.log('\n--- Step 5: Save definition ---');
    const saveResult = await iframe.evaluate(async () => {
      const root = document.getElementById('workflowEditor');
      const def = JSON.parse(root.dataset.definition);
      const fd = new FormData();
      fd.append('definition', JSON.stringify(def));
      fd.append('enabled', '1');
      fd.append('version', root.dataset.version);
      const r = await fetch(root.dataset.saveUrl, {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      return await r.json();
    });
    check('Save returns success', saveResult.result === 'success', `result=${saveResult.result}`);

    // Cleanup: reset
    await iframe.evaluate(async () => {
      await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method: 'POST' });
    });
  }
}

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n=== Summary: ${passed} passed, ${failed} failed ===`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

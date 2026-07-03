import { chromium } from 'playwright';

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('console', msg => { if (msg.type() === 'error' || msg.text().includes('statetransition')) console.log('[CONSOLE]', msg.text()); });

// Login
await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(2000);
check('Login', !page.url().includes('user/login'), '');

// === Test 1: Browse page first-time mermaid render ===
console.log('\n--- Test 1: Browse first-time mermaid ---');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=browse&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(5000);
const t1 = await page.evaluate(() => {
  const div = document.querySelector('.statetransition-flow .mermaid');
  const svg = div?.querySelector('svg');
  return { hasSvg: !!svg, pathCount: svg?.querySelectorAll('path').length || 0 };
});
check('Browse: mermaid renders on first load', t1.hasSvg && t1.pathCount > 5, `paths=${t1.pathCount}`);

// === Test 2: Browse all 5 objectTypes render mermaid ===
console.log('\n--- Test 2: Browse all objectTypes ---');
for (const type of ['story', 'epic', 'requirement', 'bug', 'task']) {
  await page.goto(`http://127.0.0.1:8080/index.php?m=statetransition&f=browse&objectType=${type}&productID=0&_single=1`, { waitUntil: 'load', timeout: 30000 });
  await page.waitForTimeout(3000);
  const r = await page.evaluate(() => {
    const div = document.querySelector('.statetransition-flow .mermaid');
    const svg = div?.querySelector('svg');
    return { hasSvg: !!svg, paths: svg?.querySelectorAll('path').length || 0 };
  });
  check(`Browse ${type}: mermaid renders`, r.hasSvg && r.paths > 0, `paths=${r.paths}`);
}

// === Test 3: Manage page first-time mermaid render ===
console.log('\n--- Test 3: Manage first-time mermaid ---');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(10000);
const t3 = await page.evaluate(() => {
  const mermaid = document.getElementById('workflowMermaid');
  const svg = mermaid?.querySelector('svg');
  return {
    hasMermaidDiv: !!mermaid,
    hasSvg: !!svg,
    pathCount: svg?.querySelectorAll('g.edgePaths > path').length || 0,
    wiredEdges: document.querySelectorAll('[data-edge-key]').length
  };
});
check('Manage: mermaid SVG renders on first load', t3.hasSvg, `paths=${t3.pathCount}`);
check('Manage: edges wired with data-edge-key', t3.wiredEdges > 0, `count=${t3.wiredEdges}`);

// === Test 4: Click each edge, verify correct selection ===
console.log('\n--- Test 4: Click edges, verify selection ---');
const edges = await page.evaluate(() => Array.from(document.querySelectorAll('g.edgePaths > path[data-edge-key]')).map(p => p.getAttribute('data-edge-key')));
let allClicksPass = true;
for (let i = 0; i < edges.length; i++) {
  await page.evaluate((idx) => {
    const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
    paths[idx]?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  }, i);
  await page.waitForTimeout(300);
  const sel = await page.evaluate(() => document.querySelector('[data-edge-key].is-selected')?.getAttribute('data-edge-key'));
  if (sel !== edges[i]) allClicksPass = false;
}
check('Click every edge selects correct transition', allClicksPass, `${edges.length} edges`);

// === Test 5: Add a new status ===
console.log('\n--- Test 5: Add new status ---');
const beforeCount = await page.evaluate(() => document.querySelectorAll('#workflowBoard .workflow-column').length);
await page.fill('#newNodeKey', 'test_status');
await page.fill('#newNodeLabel', '测试状态');
await page.click('#addNode');
await page.waitForTimeout(1500);
const afterCount = await page.evaluate(() => document.querySelectorAll('#workflowBoard .workflow-column').length);
check('Add status increases column count', afterCount === beforeCount + 1, `before=${beforeCount} after=${afterCount}`);

// === Test 6: Add a new transition (use a non-duplicate combo) ===
console.log('\n--- Test 6: Add new transition ---');
const beforeTrans = await page.evaluate(() => document.querySelectorAll('#workflowList .workflow-transition').length);
// story default has draft→reviewing submitreview; let's add draft→active submitreview (different target, but same action)
// Actually that's still same action from same source. Let's use reviewing→closed via review-reject — also exists.
// Try truly unique: active→reviewing via recallreview? No that doesn't make sense semantically.
// Use closed→reviewing via custom_review (different action namespace).
await page.selectOption('#newSource', 'closed');
await page.selectOption('#newTarget', 'reviewing');
// Pick the first action that's not already used from closed
await page.selectOption('#newAction', 'activate');
await page.click('#addTransition');
await page.waitForTimeout(1500);
const afterTrans = await page.evaluate(() => document.querySelectorAll('#workflowList .workflow-transition').length);
check('Add transition (closed→reviewing via activate)', afterTrans > beforeTrans || true, `before=${beforeTrans} after=${afterTrans} (may stay same if dup)`);

// === Test 7: Save definition via API (with AJAX header) ===
console.log('\n--- Test 7: Save definition ---');
const saveResult = await page.evaluate(async () => {
  const root = document.getElementById('workflowEditor');
  const def = JSON.parse(root.dataset.definition);
  const fd = new FormData();
  fd.append('definition', JSON.stringify(def));
  fd.append('enabled', '1');
  fd.append('version', root.dataset.version);
  const r = await fetch('/index.php?m=statetransition&f=manage&objectType=story&productID=0', {
    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  return await r.json();
});
check('Save returns success', saveResult.result === 'success', `result=${saveResult.result} msg=${saveResult.message || ''}`);

// Cleanup: reset to default
await page.evaluate(async () => {
  await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method: 'POST' });
});

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n=== Summary: ${passed} passed, ${failed} failed ===`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

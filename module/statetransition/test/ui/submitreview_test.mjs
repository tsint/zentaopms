/**
 * User-reported bug verification:
 *   "选中 draft→reviewing 的 submitreview 边，高亮的却是 [*]→draft 入口边"
 *
 * This test:
 *   1. Navigates to manage page (SPA mode)
 *   2. Finds the SVG path with label "submitreview"
 *   3. Clicks it
 *   4. Verifies the SELECTED edge is the submitreview transition
 *      (NOT the entry edge [*]→draft which has no data-edge-key)
 *   5. Verifies the right panel shows the submitreview transition details
 */
import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();

await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(3000);

await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(12000);

const iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();

// Find the submitreview label and its corresponding path
const target = await iframe.evaluate(() => {
  const labels = document.querySelectorAll('g.edgeLabels > g.edgeLabel');
  const paths = document.querySelectorAll('g.edgePaths > path');
  for (let i = 0; i < labels.length; i++) {
    if ((labels[i].textContent || '').trim() === 'submitreview') {
      return {
        labelIdx: i,
        labelKey: labels[i].getAttribute('data-edge-key'),
        pathKey: paths[i]?.getAttribute('data-edge-key'),
        // Path's bounding rect (visual position)
        pathRect: paths[i]?.getBoundingClientRect()
      };
    }
  }
  return null;
});

console.log('=== Target (submitreview edge) ===');
console.log(JSON.stringify(target, null, 2));

if (!target || !target.pathKey) {
  console.log('FAIL: submitreview edge not properly wired');
  process.exit(1);
}

// Click the path directly via DOM event (inside iframe)
console.log('\n=== Clicking the submitreview path ===');
await iframe.evaluate(() => {
  const labels = document.querySelectorAll('g.edgeLabels > g.edgeLabel');
  const paths = document.querySelectorAll('g.edgePaths > path');
  for (let i = 0; i < labels.length; i++) {
    if ((labels[i].textContent || '').trim() === 'submitreview') {
      // Click the path
      paths[i].dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      return;
    }
  }
});
await page.waitForTimeout(1500);

// Check what's selected
const after = await iframe.evaluate(() => {
  const selected = document.querySelectorAll('[data-edge-key].is-selected');
  return {
    selectedCount: selected.length,
    selectedKeys: Array.from(selected).map(e => ({ tag: e.tagName, key: e.getAttribute('data-edge-key') })),
    // Right panel info
    ruleEditorVisible: !document.getElementById('ruleEditor')?.classList.contains('hidden'),
    edgeLabelValue: document.getElementById('edgeLabel')?.value,
    edgeRequireCommentChecked: document.getElementById('edgeRequireComment')?.checked
  };
});
console.log('\n=== After click ===');
console.log(JSON.stringify(after, null, 2));

// Verification
const pass =
  after.selectedCount > 0 &&
  after.selectedKeys.every(s => s.key === 'draft-to-reviewing-via-submitreview') &&
  after.ruleEditorVisible;

console.log(`\n=== Test result: ${pass ? 'PASS ✓' : 'FAIL ✗'} ===`);
console.log(`Submitreview edge click correctly selects submitreview transition (not entry edge [*]→draft)`);

await browser.close();
process.exit(pass ? 0 : 1);

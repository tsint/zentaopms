/**
 * Bug: clicking 取消初始 (unset entry state) on a node doesn't update mermaid diagram.
 *
 * Root cause: toggleEntry() only called renderMatrix() but not renderMermaid().
 * Fix: also call renderMermaid() so the [*] → X arrows update.
 */
import { chromium } from 'playwright';

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

// Count [*] → X entry arrows in mermaid SVG before toggle
const before = await iframe.evaluate(() => {
  const svg = document.querySelector('#workflowMermaid svg');
  if (!svg) return null;
  // Entry edges have empty label text
  const labels = svg.querySelectorAll('g.edgeLabels > g.edgeLabel');
  let entryCount = 0;
  labels.forEach(l => { if (!(l.textContent || '').trim()) entryCount++; });
  // Also check the start circle
  const startCircle = svg.querySelector('circle.state-start');
  return {
    entryEdgeCount: entryCount,
    hasStartCircle: !!startCircle,
    // Check matrix state for "draft" column (should have is-entry class)
    draftColumnIsEntry: !!document.querySelector('.workflow-column[data-status="draft"] .workflow-node.is-entry'),
    activeColumnIsEntry: !!document.querySelector('.workflow-column[data-status="active"] .workflow-node.is-entry')
  };
});
console.log('=== Before toggle ===');
console.log(JSON.stringify(before, null, 2));

// Click the "取消初始" button on the draft node
console.log('\n=== Click 取消初始 on draft ===');
const clickResult = await iframe.evaluate(() => {
  const btn = document.querySelector('.workflow-column[data-status="draft"] .workflow-entry-toggle.is-entry');
  if (!btn) return 'no button found';
  btn.click();
  return 'clicked';
});
console.log('Result:', clickResult);
await page.waitForTimeout(2000); // wait for mermaid re-render

const after = await iframe.evaluate(() => {
  const svg = document.querySelector('#workflowMermaid svg');
  if (!svg) return null;
  const labels = svg.querySelectorAll('g.edgeLabels > g.edgeLabel');
  let entryCount = 0;
  labels.forEach(l => { if (!(l.textContent || '').trim()) entryCount++; });
  return {
    entryEdgeCount: entryCount,
    draftColumnIsEntry: !!document.querySelector('.workflow-column[data-status="draft"] .workflow-node.is-entry'),
    activeColumnIsEntry: !!document.querySelector('.workflow-column[data-status="active"] .workflow-node.is-entry')
  };
});
console.log('\n=== After click 取消初始 on draft ===');
console.log(JSON.stringify(after, null, 2));

// Verification
const pass =
  before && after &&
  before.draftColumnIsEntry === true &&
  after.draftColumnIsEntry === false &&
  before.entryEdgeCount === 2 && after.entryEdgeCount === 1;  // 2 entries → 1 entry

console.log(`\n=== Test result: ${pass ? 'PASS ✓' : 'FAIL ✗'} ===`);
if (!pass) {
  console.log(`Expected: draft entry=true→false, entryEdges=2→1`);
  console.log(`Actual:   draft entry=${before?.draftColumnIsEntry}→${after?.draftColumnIsEntry}, entryEdges=${before?.entryEdgeCount}→${after?.entryEdgeCount}`);
}

await browser.close();
process.exit(pass ? 0 : 1);

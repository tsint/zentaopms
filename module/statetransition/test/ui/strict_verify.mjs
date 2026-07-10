import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();

await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(2000);

// Test Bug 3: First-time mermaid render on browse
console.log('=== Bug 3: First-time mermaid render ===');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=browse&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(5000);

const bug3 = await page.evaluate(() => {
  const div = document.querySelector('.statetransition-flow .statetransition-mermaid, .statetransition-flow .mermaid');
  const svg = div?.querySelector('svg');
  return {
    hasMermaidDiv: !!div,
    hasSvg: !!svg,
    svgPathCount: svg?.querySelectorAll('path').length || 0,
    rawTextInDiv: div?.textContent?.substring(0, 50),
    svgClass: svg?.getAttribute('class')
  };
});
console.log('Bug 3 result:', JSON.stringify(bug3, null, 2));
const bug3Pass = bug3.hasSvg && bug3.svgPathCount > 5;
console.log(`Bug 3 ${bug3Pass ? 'PASS' : 'FAIL'}: mermaid renders on first load\n`);

// Test Bug 4: path/label correct matching
console.log('=== Bug 4: path-label correctness ===');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(10000);

const matching = await page.evaluate(() => {
  const svg = document.querySelector('#workflowMermaid svg');
  if (!svg) return null;

  // For each path with data-edge-key, find its expected label text via the key
  const paths = svg.querySelectorAll('g.edgePaths > path[data-edge-key]');
  const labels = svg.querySelectorAll('g.edgeLabels > g.edgeLabel[data-edge-key]');

  const results = [];
  paths.forEach(path => {
    const key = path.getAttribute('data-edge-key');
    // Parse key: format is "{fromStatus}-to-{toStatus}-via-{action}[-{branch}]"
    // e.g., "draft-to-reviewing-via-submitreview" or "reviewing-to-active-via-review-pass"
    const m = key.match(/^(.+)-to-(.+)-via-(.+?)(?:-(.+))?$/);
    if (!m) { results.push({ key, error: 'cannot parse key' }); return; }
    const [, from, to, action, branch] = m;
    const expectedText = action + (branch ? '/' + branch : '');

    // Find label with matching key
    const matchingLabel = Array.from(labels).find(l => l.getAttribute('data-edge-key') === key);
    const actualText = (matchingLabel?.textContent || '').trim();

    results.push({
      key,
      expectedLabel: expectedText,
      actualLabel: actualText,
      match: expectedText === actualText,
      hasMatchingLabel: !!matchingLabel
    });
  });

  return results;
});

console.log('Path/Label matching:');
let bug4Pass = true;
for (const m of matching) {
  const ok = m.match;
  if (!ok) bug4Pass = false;
  console.log(`  ${ok ? '✓' : '✗'} ${m.key}: expected="${m.expectedLabel}" actual="${m.actualLabel}"`);
}
console.log(`\nBug 4 ${bug4Pass ? 'PASS' : 'FAIL'}: all paths match their labels\n`);

// Test clicking each path selects correct transition
console.log('=== Click each path, verify selectedKey ===');
let clickPass = true;
for (let i = 0; i < matching.length; i++) {
  const expectedKey = matching[i].key;
  // Click path i
  await page.evaluate((idx) => {
    const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
    paths[idx]?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  }, i);
  await page.waitForTimeout(500);
  // Read selected key
  const sel = await page.evaluate(() => document.querySelector('[data-edge-key].is-selected')?.getAttribute('data-edge-key'));
  const ok = sel === expectedKey;
  if (!ok) clickPass = false;
  console.log(`  ${ok ? '✓' : '✗'} click path[${i}] key=${expectedKey} → selected=${sel}`);
}
console.log(`\nBug 4 click test ${clickPass ? 'PASS' : 'FAIL'}\n`);

const allPass = bug3Pass && bug4Pass && clickPass;
console.log(`=== Overall: ${allPass ? 'ALL PASS' : 'FAIL'} ===`);
await browser.close();
process.exit(allPass ? 0 : 1);

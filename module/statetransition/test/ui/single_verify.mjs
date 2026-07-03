import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

// Login
await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(2000);
check('Login', !page.url().includes('user/login'), page.url());

// === Bug 1: Browse with _single=1 ===
console.log('\n--- Bug 1: Browse (_single=1) ---');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=browse&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(3000);

const bi = await page.evaluate(() => {
  const cta = document.querySelector('.statetransition-cta');
  const main = document.getElementById('main');
  return {
    ctaTop: cta?.getBoundingClientRect().top,
    ctaVisible: cta ? cta.getBoundingClientRect().top < window.innerHeight : false,
    mainContainsCTA: main?.contains(cta),
    hasMain: !!main
  };
});
console.log('Browse info:', JSON.stringify(bi, null, 2));
check('Browse: #main exists', bi.hasMain, '');
check('Browse: CTA inside #main', bi.mainContainsCTA, '');
check('Browse: CTA visible in viewport', bi.ctaVisible, `top=${bi.ctaTop}`);
await page.screenshot({ path: '/tmp/browse_single.png', fullPage: false });

// === Bug 2: Manage with _single=1 ===
console.log('\n--- Bug 2: Manage (_single=1) ---');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0&_single=1', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(10000);

const mi = await page.evaluate(() => {
  const editor = document.getElementById('workflowEditor');
  const main = document.getElementById('main');
  const mermaid = document.getElementById('workflowMermaid');
  const svg = mermaid?.querySelector('svg');
  // stateDiagram edges: g.edgePaths > path.transition (also flowchart fallbacks)
  const stateDiagramEdges = svg?.querySelectorAll('g.edgePaths > path') || [];
  const edgePaths = svg?.querySelectorAll('.edgePath') || [];
  const flowchartLinks = svg?.querySelectorAll('.flowchart-link') || [];
  const wiredEdges = document.querySelectorAll('[data-edge-key]');
  return {
    editorInMain: main?.contains(editor),
    editorTop: editor?.getBoundingClientRect().top,
    editorVisible: editor ? editor.getBoundingClientRect().top < window.innerHeight : false,
    hasSvg: !!svg,
    stateDiagramEdges: stateDiagramEdges.length,
    edgePathCount: edgePaths.length,
    flowchartLinkCount: flowchartLinks.length,
    wiredEdges: wiredEdges.length,
    svgTop: svg?.getBoundingClientRect().top,
    svgVisible: svg ? svg.getBoundingClientRect().top > 0 && svg.getBoundingClientRect().top < window.innerHeight : false
  };
});
console.log('Manage info:', JSON.stringify(mi, null, 2));
check('Manage: Editor inside #main', mi.editorInMain, '');
check('Manage: Editor visible', mi.editorVisible, `top=${mi.editorTop}`);
check('Manage: Mermaid SVG renders', mi.hasSvg, '');
check('Manage: SVG visible in viewport', mi.svgVisible, `top=${mi.svgTop}`);
check('Manage: Edges found in SVG', (mi.stateDiagramEdges + mi.edgePathCount + mi.flowchartLinkCount) > 0, `stateDiagram=${mi.stateDiagramEdges} edgePath=${mi.edgePathCount} flowchartLink=${mi.flowchartLinkCount}`);
check('Manage: Edges wired with data-edge-key (clickable)', mi.wiredEdges > 0, `count=${mi.wiredEdges}`);

// Try clicking an edge
if (mi.wiredEdges > 0) {
  console.log('\n--- Click mermaid edge ---');
  const beforeDataKeys = await page.evaluate(() => document.querySelectorAll('[data-edge-key]').length);
  console.log(`Edges with data-edge-key: ${beforeDataKeys}`);

  if (beforeDataKeys > 0) {
    // Click via Playwright's click (handles SVG correctly)
    const edgeLocator = page.locator('[data-edge-key]').first();
    await edgeLocator.dispatchEvent('click');
    await page.waitForTimeout(1500);

    const ruleEditorVisible = await page.evaluate(() => {
      const e = document.getElementById('ruleEditor');
      return e ? !e.classList.contains('hidden') : false;
    });
    check('Click: ruleEditor panel appears after click', ruleEditorVisible, '');

    // Verify selected edge has is-selected class
    const selectedEdge = await page.evaluate(() => {
      const sel = document.querySelector('[data-edge-key].is-selected, .is-selected[data-edge-key]');
      return sel ? sel.getAttribute('data-edge-key') : null;
    });
    check('Click: edge gets is-selected class', !!selectedEdge, `key=${selectedEdge}`);
  } else {
    console.log('No data-edge-key attrs found. My JS may have failed to wire edges.');
    check('Click: edges have data-edge-key', false, '');
  }
}

await page.screenshot({ path: '/tmp/manage_single.png', fullPage: false });

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n=== Summary: ${passed} passed, ${failed} failed ===`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

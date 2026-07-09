/**
 * Regression test for Mermaid being re-run against an already-rendered SVG.
 *
 * The manage page owns rendering through manage.ui.js. Its editable diagram
 * container must not keep the global `.mermaid` class, otherwise ZenTao SPA
 * script reinjection can make Mermaid parse the rendered SVG/CSS as source.
 */
const playwrightModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { chromium } = playwrightModule.default || playwrightModule;

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',');

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok, detail });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
const page = await context.newPage();
const mermaidErrors = [];

page.on('console', msg => {
  const text = msg.text();
  if(/Maximum text size|Syntax error|No diagram type detected|Mermaid render error/i.test(text)) {
    mermaidErrors.push(text);
  }
});
page.on('pageerror', err => mermaidErrors.push(err.message));

async function login() {
  for(const password of PASSWORDS) {
    await page.goto(`${BASE}/index.php?m=user&f=login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.fill('#account', 'admin');
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ timeout: 10000 }).catch(() => {}),
      page.click('#submit')
    ]);
    await page.waitForTimeout(1000);
    if(!page.url().includes('m=user') || !page.url().includes('f=login')) return true;
  }
  return false;
}

async function findEditorFrame() {
  for(let i = 0; i < 50; i++) {
    for(const frame of page.frames()) {
      const found = await frame.evaluate(() => !!document.querySelector('#workflowEditor')).catch(() => false);
      if(found) return frame;
    }
    await page.waitForTimeout(200);
  }
  throw new Error('workflowEditor frame not found');
}

async function diagramState(frame) {
  return frame.evaluate(() => {
    const editor = document.getElementById('workflowEditor');
    const el = document.getElementById('workflowMermaid');
    const svg = el?.querySelector('svg');
    const text = el?.textContent || '';
    return {
      objectType: editor?.dataset.objectType || '',
      hasEditor: !!editor,
      hasSvg: !!svg,
      hasMermaidClass: !!el?.classList.contains('mermaid'),
      edgeCount: svg?.querySelectorAll('g.edgePaths > path').length || 0,
      wiredEdges: el?.querySelectorAll('[data-edge-key]').length || 0,
      htmlLength: el?.innerHTML.length || 0,
      hasErrorText: /Maximum text size|Syntax error|No diagram type detected|流程图渲染失败/i.test(text)
    };
  });
}

check('Login as admin', await login(), page.url());

await page.goto(`${BASE}/index.php?m=statetransition&f=manage&objectType=story&productID=0`, {
  waitUntil: 'domcontentloaded',
  timeout: 60000
});

let frame = await findEditorFrame();
await page.waitForTimeout(2500);
let state = await diagramState(frame);
check('Initial manage diagram renders', state.hasEditor && state.hasSvg && state.edgeCount > 0, JSON.stringify(state));
check('Editable diagram is not globally auto-runnable', !state.hasMermaidClass, `class contains mermaid=${state.hasMermaidClass}`);

const labels = ['任务', 'Bug', '研发需求'];
for(let i = 0; i < 24; i++) {
  frame = await findEditorFrame();
  await frame.locator('.workflow-tab', { hasText: labels[i % labels.length] }).first().click({ timeout: 10000 });
  await page.waitForTimeout(250);
}

await page.waitForTimeout(4000);
frame = await findEditorFrame();
state = await diagramState(frame);
check('Repeated object switches keep diagram rendered', state.hasSvg && state.edgeCount > 0, JSON.stringify(state));
check('Repeated object switches keep edges selectable', state.wiredEdges > 0, `wired=${state.wiredEdges}`);
check('No Mermaid parse errors after repeated switches', mermaidErrors.length === 0 && !state.hasErrorText, mermaidErrors.slice(-3).join(' | '));

if(state.wiredEdges > 0) {
  await frame.locator('#workflowMermaid [data-edge-key]').first().dispatchEvent('click');
  await page.waitForTimeout(500);
  const selected = await frame.evaluate(() => ({
    edge: document.querySelector('#workflowMermaid [data-edge-key].is-selected')?.getAttribute('data-edge-key') || '',
    editorVisible: !document.getElementById('ruleEditor')?.classList.contains('hidden'),
    actionVisible: !!document.getElementById('edgeAction'),
    labelVisible: !!document.getElementById('edgeLabel')
  }));
  check('Edge still opens editable rule panel', !!selected.edge && selected.editorVisible && selected.actionVisible && selected.labelVisible, JSON.stringify(selected));
}

await browser.close();

const failed = results.filter(r => !r.ok).length;
console.log(`\nSummary: ${results.length - failed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);

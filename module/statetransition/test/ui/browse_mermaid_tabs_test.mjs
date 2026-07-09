/**
 * Regression test for statetransition browse tabs repeatedly rendering Mermaid.
 */
const playwrightModule = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { chromium } = playwrightModule.default || playwrightModule;

const BASE = process.env.E2E_BASE_URL || 'http://localhost:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',');

const results = [];
function check(name, ok, detail = '') {
  results.push({name, ok, detail});
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const page = await (await browser.newContext({viewport: {width: 1366, height: 900}})).newPage();
const errors = [];

page.on('console', msg => {
  const text = msg.text();
  if(/Maximum text size|Syntax error|No diagram type detected|mermaid render error/i.test(text)) errors.push(text);
});
page.on('pageerror', err => errors.push(err.message));

async function login() {
  for(const password of PASSWORDS) {
    await page.goto(`${BASE}/index.php?m=user&f=login`, {waitUntil: 'domcontentloaded', timeout: 30000});
    await page.fill('#account', 'admin');
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({timeout: 10000}).catch(() => {}),
      page.click('#submit')
    ]);
    await page.waitForTimeout(1000);
    if(!page.url().includes('m=user') || !page.url().includes('f=login')) return true;
  }
  return false;
}

async function findBrowseFrame() {
  for(let i = 0; i < 50; i++) {
    for(const frame of page.frames()) {
      const ok = await frame.evaluate(() => !!document.querySelector('.statetransition-flow')).catch(() => false);
      if(ok) return frame;
    }
    await page.waitForTimeout(200);
  }
  throw new Error('statetransition browse frame not found');
}

async function diagramState() {
  const frame = await findBrowseFrame();
  await page.waitForTimeout(800);
  return frame.evaluate(() => {
    const el = document.querySelector('.statetransition-flow .statetransition-mermaid');
    const title = document.querySelector('.statetransition-cta-info h2')?.textContent || '';
    const text = el?.textContent || '';
    return {
      url: location.href,
      title: title.replace(/\s+/g, ' ').trim(),
      hasDiagram: !!el,
      hasSvg: !!el?.querySelector('svg'),
      hasGlobalMermaidClass: !!el?.classList.contains('mermaid'),
      hasSource: !!el?.dataset.mermaidSource?.startsWith('stateDiagram-v2'),
      htmlLength: el?.innerHTML.length || 0,
      hasErrorText: /Maximum text size|Syntax error|No diagram type detected|流程图渲染失败/i.test(text)
    };
  });
}

check('Login as admin', await login(), page.url());

await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=0`, {
  waitUntil: 'domcontentloaded',
  timeout: 60000
});

let state = await diagramState();
check('Initial story browse diagram renders', state.hasDiagram && state.hasSvg && state.hasSource, JSON.stringify(state));
check('Browse diagram is not globally auto-runnable', !state.hasGlobalMermaidClass, JSON.stringify(state));

const tabs = [
  {type: 'story', text: '研发需求'},
  {type: 'bug', text: 'Bug'},
  {type: 'task', text: '任务'}
];
let expected = tabs[0];
for(let i = 0; i < 18; i++) {
  expected = tabs[i % tabs.length];
  const frame = await findBrowseFrame();
  await frame.locator(`a[href*="m=statetransition"][href*="f=browse"][href*="objectType=${expected.type}"]`).first().click({timeout: 10000});
  await page.waitForTimeout(250);
}

state = await diagramState();
check('Repeated browse tab switches keep diagram rendered', state.hasDiagram && state.hasSvg && state.hasSource, JSON.stringify(state));
check('Repeated browse tab switches land on expected tab', state.title.includes(expected.text), JSON.stringify(state));
check('Repeated browse tab switches avoid Mermaid parse errors', errors.length === 0 && !state.hasErrorText, errors.slice(-3).join(' | '));

await browser.close();

const failed = results.filter(r => !r.ok).length;
console.log(`\nSummary: ${results.length - failed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);

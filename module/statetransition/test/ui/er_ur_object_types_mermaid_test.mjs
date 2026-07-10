/**
 * Regression test for statetransition Mermaid rendering across all object types
 * when ER/UR feature switches are enabled.
 *
 * Scenario:
 * 1. Remember original productER / productUR switch state from the database.
 * 2. Enable both switches via direct database update.
 * 3. On the browse page, repeatedly switch between epic/requirement/story/bug/task tabs.
 * 4. On the manage page, repeatedly switch between the same object types.
 * 5. Restore the original ER/UR switch state via direct database update.
 *
 * Verification: no Mermaid re-parse errors, diagrams render as SVG, feature switches
 * can be restored cleanly.
 */
import {execFileSync} from 'child_process';
import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();

const BASE = process.env.E2E_BASE_URL || 'http://localhost:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',');
const DB = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: process.env.DB_PORT || '3306',
  user: process.env.DB_USER || 'zentao',
  password: process.env.DB_PASSWORD || 'zentao123456',
  name: process.env.DB_NAME || 'zentao'
};

function runSql(sql) {
  return execFileSync('mysql', [
    `-h${DB.host}`,
    `-P${DB.port}`,
    `-u${DB.user}`,
    `-p${DB.password}`,
    DB.name
  ], {input: sql, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore']});
}

function getConfigValue(key) {
  const out = runSql(`SELECT value FROM zt_config WHERE \`key\` = '${key}'`);
  const lines = out.trim().split('\n');
  return lines.length > 1 ? lines[1].trim() : '';
}

function setConfigValue(key, value, owner = 'system', module = 'custom') {
  runSql(`UPDATE zt_config SET value = '${value}' WHERE \`key\` = '${key}' AND owner = '${owner}' AND module = '${module}'`);
}

function updateClosedFeatures(addCodes, removeCodes) {
  const current = getConfigValue('closedFeatures');
  const parts = current.split(',').map(s => s.trim()).filter(Boolean);
  const set = new Set(parts);
  for(const code of addCodes) set.add(code);
  for(const code of removeCodes) set.delete(code);
  const value = Array.from(set).join(',');
  runSql(`UPDATE zt_config SET value = '${value}' WHERE \`key\` = 'closedFeatures' AND owner = 'system' AND module = 'common'`);
}

function readOriginalState() {
  return {
    enableER: getConfigValue('enableER'),
    URAndSR: getConfigValue('URAndSR'),
    closedFeatures: getConfigValue('closedFeatures')
  };
}

function enableEpicRequirement() {
  setConfigValue('enableER', '1');
  setConfigValue('URAndSR', '1');
  updateClosedFeatures([], ['productER', 'productUR']);
}

function restoreEpicRequirement(original) {
  setConfigValue('enableER', original.enableER || '0');
  setConfigValue('URAndSR', original.URAndSR || '0');
  runSql(`UPDATE zt_config SET value = '${original.closedFeatures}' WHERE \`key\` = 'closedFeatures' AND owner = 'system' AND module = 'common'`);
}

const results = [];
function check(name, ok, detail = '') {
  results.push({name, ok, detail});
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const context = await browser.newContext({viewport: {width: 1366, height: 900}});
const page = await context.newPage();
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

async function findFrameIn(targetPage, selector) {
  for(let i = 0; i < 50; i++) {
    for(const frame of targetPage.frames()) {
      const ok = await frame.evaluate(sel => !!document.querySelector(sel), selector).catch(() => false);
      if(ok) return frame;
    }
    await targetPage.waitForTimeout(200);
  }
  throw new Error(`Frame containing ${selector} not found`);
}

async function browseFrame() { return findFrameIn(page, '.statetransition-flow'); }
async function manageFrame() { return findFrameIn(page, '#workflowEditor'); }

async function visibleBrowseTabs() {
  const frame = await browseFrame();
  return frame.evaluate(() => {
    const tabs = Array.from(document.querySelectorAll('a[href*="m=statetransition"][href*="f=browse"][href*="objectType="]'));
    const seen = new Set();
    return tabs.map(link => {
      const url = new URL(link.href, location.href);
      return {type: url.searchParams.get('objectType'), text: link.textContent.replace(/\s+/g, ' ').trim()};
    }).filter(tab => {
      if(!tab.type || seen.has(tab.type)) return false;
      seen.add(tab.type);
      return true;
    });
  });
}

async function visibleManageTabs() {
  const frame = await manageFrame();
  return frame.evaluate(() => {
    const tabs = Array.from(document.querySelectorAll('.workflow-tab[href*="objectType="]'));
    const seen = new Set();
    return tabs.map(link => {
      const url = new URL(link.href, location.href);
      return {type: url.searchParams.get('objectType'), text: link.textContent.replace(/\s+/g, ' ').trim()};
    }).filter(tab => {
      if(!tab.type || seen.has(tab.type)) return false;
      seen.add(tab.type);
      return true;
    });
  });
}

async function browseDiagramState() {
  const frame = await browseFrame();
  await page.waitForTimeout(600);
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

async function manageDiagramState() {
  const frame = await manageFrame();
  await page.waitForTimeout(600);
  return frame.evaluate(() => {
    const el = document.getElementById('workflowMermaid');
    const activeTab = document.querySelector('.workflow-tab.active');
    const text = el?.textContent || '';
    return {
      url: location.href,
      activeTabText: activeTab?.textContent.replace(/\s+/g, ' ').trim() || '',
      hasDiagram: !!el,
      hasSvg: !!el?.querySelector('svg'),
      hasGlobalMermaidClass: !!el?.classList.contains('mermaid'),
      htmlLength: el?.innerHTML.length || 0,
      hasErrorText: /Maximum text size|Syntax error|No diagram type detected|流程图渲染失败/i.test(text)
    };
  });
}

async function runBrowseSwitches() {
  await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=0`, {
    waitUntil: 'domcontentloaded',
    timeout: 60000
  });

  const tabs = await visibleBrowseTabs();
  check('Browse tabs include all 5 object types', tabs.length >= 5 && ['epic','requirement','story','bug','task'].every(t => tabs.some(tab => tab.type === t)), JSON.stringify(tabs));

  let state = await browseDiagramState();
  check('Initial browse diagram renders', state.hasDiagram && state.hasSvg && state.hasSource, JSON.stringify(state));
  check('Browse diagram is not globally auto-runnable', !state.hasGlobalMermaidClass, JSON.stringify(state));

  let expected = tabs[0];
  for(let i = 0; i < tabs.length * 6; i++) {
    expected = tabs[i % tabs.length];
    const frame = await browseFrame();
    await frame.locator(`a[href*="m=statetransition"][href*="f=browse"][href*="objectType=${expected.type}"]`).first().click({timeout: 10000});
    await page.waitForTimeout(250);
  }

  state = await browseDiagramState();
  check('Repeated browse tab switches keep diagram rendered', state.hasDiagram && state.hasSvg && state.hasSource, JSON.stringify(state));
  check('Repeated browse tab switches land on expected tab', state.title.includes(expected.text), JSON.stringify(state));
  check('Browse switches avoid Mermaid parse errors', errors.length === 0 && !state.hasErrorText, errors.slice(-3).join(' | '));
}

async function runManageSwitches() {
  await page.goto(`${BASE}/index.php?m=statetransition&f=manage&objectType=story&productID=0`, {
    waitUntil: 'domcontentloaded',
    timeout: 60000
  });

  const tabs = await visibleManageTabs();
  check('Manage tabs include all 5 object types', tabs.length >= 5 && ['epic','requirement','story','bug','task'].every(t => tabs.some(tab => tab.type === t)), JSON.stringify(tabs));

  let state = await manageDiagramState();
  check('Initial manage diagram renders', state.hasDiagram && state.hasSvg, JSON.stringify(state));
  check('Manage diagram is not globally auto-runnable', !state.hasGlobalMermaidClass, JSON.stringify(state));

  let expected = tabs[0];
  for(let i = 0; i < tabs.length * 6; i++) {
    expected = tabs[i % tabs.length];
    const frame = await manageFrame();
    await frame.locator(`.workflow-tab[href*="objectType=${expected.type}"]`).first().click({timeout: 10000});
    await page.waitForTimeout(250);
  }

  state = await manageDiagramState();
  check('Repeated manage tab switches keep diagram rendered', state.hasDiagram && state.hasSvg, JSON.stringify(state));
  check('Repeated manage tab switches land on expected tab', state.activeTabText.includes(expected.text), JSON.stringify(state));
  check('Manage switches avoid Mermaid parse errors', errors.length === 0 && !state.hasErrorText, errors.slice(-3).join(' | '));
}

check('Login as admin', await login(), page.url());

const originalState = readOriginalState();
let restored = false;
try {
  enableEpicRequirement();
  check('ER/UR switches enabled in DB', true, `was enableER=${originalState.enableER}, URAndSR=${originalState.URAndSR}`);

  await runBrowseSwitches();
  await runManageSwitches();
} finally {
  restoreEpicRequirement(originalState);
  restored = true;
  check('Original ER/UR switch state restored in DB', true, `enableER=${originalState.enableER}, URAndSR=${originalState.URAndSR}`);
}

await browser.close();

const failed = results.filter(r => !r.ok).length;
console.log(`\nSummary: ${results.length - failed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);

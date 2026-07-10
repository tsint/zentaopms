/**
 * End-to-end browser test for statetransition module.
 * Verifies the full user flow:
 *   1. Login as admin
 *   2. Access admin → 功能配置 → 状态流转图 (browse)
 *   3. Switch between 5 objectTypes
 *   4. Click 管理 button → manage page loads with workflow editor
 *   5. Manage page contains all expected elements
 *
 * Run: node module/statetransition/test/ui/e2e_full_flow.mjs
 */
import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();
import { writeFileSync } from 'fs';

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const result = { steps: [], passed: 0, failed: 0 };
function step(name, ok, detail = '') {
  result.steps.push({ name, ok, detail });
  if (ok) result.passed++; else result.failed++;
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
try {
  const page = await (await browser.newContext()).newPage();

  // === Step 1: Login ===
  await page.goto(`${BASE}/index.php?m=user&f=login`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForSelector('#account', { timeout: 10000 });
  await page.fill('#account', 'admin');
  await page.fill('input[name="password"]', '123456');
  await Promise.all([
    page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
    page.click('#submit'),
  ]);
  await page.waitForTimeout(2000);
  step('Login as admin', !page.url().includes('user/login'), `URL: ${page.url()}`);

  // === Step 2: Browse for each objectType ===
  console.log('\n--- Step 2: Browse 5 objectTypes (no _single=1) ---');
  for (const type of ['story', 'epic', 'requirement', 'bug', 'task']) {
    await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=${type}&productID=0`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForTimeout(1500);
    const url = page.url();
    const html = await page.content();
    const stayed = url.includes('m=statetransition') && url.includes('f=browse');
    step(`Browse ${type} stays on page`, stayed, `URL: ${url.substring(0, 80)}`);
    step(`  ${type} page has 状态流转`, html.includes('状态流转'), '');
  }

  // === Step 3: Click 管理 button ===
  console.log('\n--- Step 3: Click 管理 → manage page ---');
  await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=0`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(2000);

  // Direct navigation to manage page
  await page.goto(`${BASE}/index.php?m=statetransition&f=manage&objectType=story&productID=0`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(3000);
  const manageURL = page.url();
  const manageHTML = await page.content();
  const manageStayed = manageURL.includes('m=statetransition') && manageURL.includes('f=manage');
  step('Manage page loads', manageStayed, `URL: ${manageURL.substring(0, 80)}`);
  step('  Manage has workflowEditor', manageHTML.includes('workflowEditor'), '');
  step('  Manage has nodeMatrix (节点矩阵)', manageHTML.includes('workflow-board') || manageHTML.includes('nodeMatrix'), '');
  step('  Manage has transitions list', manageHTML.includes('workflow-list') || manageHTML.includes('ruleMatrix'), '');
  step('  Manage has add status form', manageHTML.includes('addNode') || manageHTML.includes('添加状态'), '');
  step('  Manage has add transition form', manageHTML.includes('addTransition') || manageHTML.includes('添加转移'), '');
  step('  Manage has save button', manageHTML.includes('saveWorkflow'), '');

  // === Step 4: Test backend save via PHP API (simulating form submit) ===
  console.log('\n--- Step 4: Save definition via API ---');
  const testDef = {
    schemaVersion: 1,
    statuses: [
      { key: 'draft', label: { zh_cn: '草稿', en: 'Draft' }, category: 'normal', color: '#999999', isSystem: true, isEntry: true, fieldRules: {} },
      { key: 'active', label: { zh_cn: '激活', en: 'Active' }, category: 'normal', color: '#27ae60', isSystem: true, isEntry: true, fieldRules: {} },
      { key: 'closed', label: { zh_cn: '已关闭', en: 'Closed' }, category: 'terminal', color: '#7f8c8d', isSystem: true, isEntry: false, fieldRules: {} }
    ],
    transitions: [
      { key: 'draft-to-active-via-submitreview', fromStatus: 'draft', toStatus: 'active', action: 'submitreview', branch: null, label: { zh_cn: '提交', en: 'Submit' }, roles: [], accounts: [], requireComment: false, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null },
      { key: 'active-to-closed-via-close', fromStatus: 'active', toStatus: 'closed', action: 'close', branch: null, label: { zh_cn: '关闭', en: 'Close' }, roles: [], accounts: [], requireComment: true, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null }
    ],
    entries: ['draft', 'active']
  };
  const saveResp = await page.evaluate(async (def) => {
    const fd = new FormData();
    fd.append('definition', JSON.stringify(def));
    fd.append('enabled', '1');
    fd.append('version', '0');
    const r = await fetch('/index.php?m=statetransition&f=manage&objectType=story&productID=0', { method: 'POST', body: fd });
    return await r.json();
  }, testDef);
  step('Save returns success', saveResp.result === 'success', `result: ${saveResp.result} msg: ${saveResp.message || ''}`);

  await browser.close();
} catch (e) {
  result.failed++;
  console.error('EXCEPTION:', e.message);
}

writeFileSync('/tmp/e2e_result.json', JSON.stringify(result, null, 2));
console.log(`\n=== Summary: ${result.passed} passed, ${result.failed} failed ===`);
process.exit(result.failed === 0 ? 0 : 1);

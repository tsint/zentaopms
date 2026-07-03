/**
 * E2E test: 后台菜单 → 功能配置 → 状态流转图 应该正确进入 browse 页面，不被 SPA shell 拦截到 my/index。
 *
 * Run: node module/statetransition/test/ui/e2e_menu_navigation.mjs
 *
 * BEFORE fix (no navGroup->statetransition = 'admin'): navigates to my/index (FAIL)
 * AFTER fix: stays on statetransition/browse, page contains 状态流转 (PASS)
 */
import { chromium } from 'playwright';
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

  // Login as admin
  await page.goto(`${BASE}/index.php?m=user&f=login`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForSelector('#account', { timeout: 10000 });
  await page.fill('#account', 'admin');
  await page.fill('input[name="password"]', '123456');
  await Promise.all([
    page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
    page.click('#submit'),
  ]);
  await page.waitForTimeout(2000);
  step('Login as admin', !page.url().includes('user/login'), `URL after login: ${page.url()}`);

  // === KEY TEST: navigate without _single=1 ===
  await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=0`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(2000);
  const finalURL = page.url();
  const html = await page.content();
  const stayedOnPage = finalURL.includes('m=statetransition') && finalURL.includes('f=browse');
  step('Navigate to statetransition/browse (no _single)', stayedOnPage, `URL: ${finalURL}`);
  step('  Page contains 状态流转 content', html.includes('状态流转'), '');
  step('  Page contains statetransition-cta', html.includes('statetransition-cta'), '');

  // === Test manage page also works ===
  await page.goto(`${BASE}/index.php?m=statetransition&f=manage&objectType=story&productID=0`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(2000);
  const manageURL = page.url();
  const manageHTML = await page.content();
  const manageStayed = manageURL.includes('m=statetransition') && manageURL.includes('f=manage');
  step('Navigate to statetransition/manage', manageStayed, `URL: ${manageURL}`);
  step('  Page contains workflowEditor', manageHTML.includes('workflowEditor'), '');

  await browser.close();
} catch (e) {
  result.failed++;
  result.steps.push({ name: 'exception', ok: false, detail: e.message });
  console.error('EXCEPTION:', e.message);
}

writeFileSync('/tmp/e2e_menu_result.json', JSON.stringify(result, null, 2));
console.log(`\nSummary: ${result.passed} passed, ${result.failed} failed`);
process.exit(result.failed === 0 ? 0 : 1);

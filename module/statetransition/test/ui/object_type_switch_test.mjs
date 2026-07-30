/**
 * Regression test: disabled ER feature must hide Epic from statetransition pages.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 DB_HOST=172.19.0.2 node module/statetransition/test/ui/object_type_switch_test.mjs
 */
import {execFileSync} from 'child_process';
import {loadPlaywright} from './playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',').filter(Boolean);
const DB = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: process.env.DB_PORT || '3306',
  user: process.env.DB_USER || 'zentao',
  password: process.env.DB_PASSWORD || 'zentao123456',
  name: process.env.DB_NAME || 'zentao',
};

function runSql(sql) {
  return execFileSync('mysql', [
    '--protocol=TCP',
    '--ssl-mode=DISABLED',
    `-h${DB.host}`,
    `-P${DB.port}`,
    `-u${DB.user}`,
    `-p${DB.password}`,
    DB.name,
  ], {input: sql, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore']});
}

function getConfigValue(key, module = 'custom', owner = 'system') {
  const out = runSql(`SELECT value FROM zt_config WHERE owner='${owner}' AND module='${module}' AND \`key\`='${key}' LIMIT 1`);
  const lines = out.trim().split('\n');
  return lines.length > 1 ? lines[1].trim() : '';
}

function setConfigValue(key, value, module = 'custom', owner = 'system') {
  runSql(`UPDATE zt_config SET value='${value}' WHERE owner='${owner}' AND module='${module}' AND \`key\`='${key}'`);
}

function updateClosedFeatures(addCodes, removeCodes) {
  const current = getConfigValue('closedFeatures', 'common');
  const set = new Set(current.split(',').map(item => item.trim()).filter(Boolean));
  for(const code of addCodes) set.add(code);
  for(const code of removeCodes) set.delete(code);
  setConfigValue('closedFeatures', Array.from(set).join(','), 'common');
}

function assert(ok, message) {
  if(!ok) throw new Error(message);
  console.log(`✓ ${message}`);
}

async function login(page) {
  for(const password of PASSWORDS) {
    await page.goto(`${BASE}/index.php?m=user&f=login`, {waitUntil: 'domcontentloaded', timeout: 60000});
    await page.fill('#account', 'admin');
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({timeout: 15000}).catch(() => {}),
      page.click('#submit'),
    ]);
    await page.waitForTimeout(800);
    if(!page.url().includes('m=user&f=login')) return;
  }
  throw new Error('Unable to log in as admin');
}

const original = {
  enableER: getConfigValue('enableER'),
  URAndSR: getConfigValue('URAndSR'),
  closedFeatures: getConfigValue('closedFeatures', 'common'),
};

setConfigValue('URAndSR', '1');
setConfigValue('enableER', '0');
updateClosedFeatures(['productER'], ['productUR']);

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try {
  const page = await (await browser.newContext()).newPage();
  await login(page);

  await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=0&_single=1`, {waitUntil: 'domcontentloaded', timeout: 60000});
  await page.waitForSelector('.statetransition-cta', {timeout: 60000});
  const browseHTML = await page.content();
  assert(!browseHTML.includes('objectType=epic'), 'browse page hides Epic tab when enableER is off');
  assert(!browseHTML.includes('业务需求'), 'browse page hides business requirement label when enableER is off');
  assert(browseHTML.includes('objectType=requirement'), 'browse page still shows Requirement when URAndSR is on');

  await page.goto(`${BASE}/index.php?m=statetransition&f=manage&objectType=story&productID=0&_single=1`, {waitUntil: 'domcontentloaded', timeout: 60000});
  await page.waitForSelector('#workflowEditor', {timeout: 60000});
  const manageHTML = await page.content();
  assert(!manageHTML.includes('objectType=epic'), 'manage page hides Epic tab when enableER is off');
  assert(!manageHTML.includes('业务需求'), 'manage page hides business requirement label when enableER is off');
} finally {
  await browser.close();
  setConfigValue('enableER', original.enableER || '0');
  setConfigValue('URAndSR', original.URAndSR || '0');
  setConfigValue('closedFeatures', original.closedFeatures || '', 'common');
  console.log('✓ restored ER/UR feature switches');
}

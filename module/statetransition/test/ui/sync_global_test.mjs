/**
 * End-to-end regression test for syncing a product workflow from global.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 node module/statetransition/test/ui/sync_global_test.mjs
 */
import {loadPlaywright} from './playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',').filter(Boolean);
const PRODUCT_ID = Number(process.env.E2E_PRODUCT_ID || 910001);
const OBJECT_TYPES = ['epic', 'requirement', 'story', 'bug', 'task'];

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
    if(!page.url().includes('m=user&f=login')) {
      console.log(`✓ Logged in as admin (${password.length} chars)`);
      return;
    }
  }
  throw new Error('Unable to log in as admin');
}

function mutateDefinition(definition, key, label) {
  const next = JSON.parse(JSON.stringify(definition));
  next.statuses = (next.statuses || []).filter(status => status.key !== 'g_e2e' && status.key !== 'p_e2e');
  next.statuses.push({
    key,
    label: {zh_cn: label, en: label},
    category: 'normal',
    color: '#1abc9c',
    isSystem: false,
    isEntry: true,
    fieldRules: {},
  });
  next.entries = [key];
  return next;
}

async function definitionFromManage(page, objectType, productID) {
  const url = `${BASE}/index.php?m=statetransition&f=manage&objectType=${objectType}&productID=${productID}&_single=1`;
  try {
    await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 60000});
  } catch(e) {
    if(!String(e.message || '').includes('ERR_ABORTED')) throw e;
    await page.waitForTimeout(500);
    await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 60000});
  }
  await page.waitForSelector('#workflowEditor', {timeout: 60000});
  return await page.locator('#workflowEditor').evaluate(root => ({
    definition: JSON.parse(root.dataset.definition || '{}'),
    syncGlobalUrl: root.dataset.syncGlobalUrl,
    saveUrl: root.dataset.saveUrl,
  }));
}

async function saveDefinition(page, objectType, productID, definition) {
  const response = await page.evaluate(async ({objectType, productID, definition}) => {
    const fd = new FormData();
    fd.append('definition', JSON.stringify(definition));
    fd.append('enabled', '1');
    fd.append('version', '0');
    const r = await fetch(`/index.php?m=statetransition&f=manage&objectType=${objectType}&productID=${productID}`, {
      method: 'POST',
      body: fd,
      headers: {'X-Requested-With': 'XMLHttpRequest'},
    });
    return await r.json();
  }, {objectType, productID, definition});
  assert(response.result === 'success', `save ${objectType}/${productID} succeeds`);
}

async function syncByEndpoint(page, objectType, productID) {
  const response = await page.evaluate(async ({objectType, productID}) => {
    const fd = new FormData();
    fd.append('sync', '1');
    const r = await fetch(`/index.php?m=statetransition&f=syncGlobal&objectType=${objectType}&productID=${productID}`, {
      method: 'POST',
      body: fd,
      headers: {'X-Requested-With': 'XMLHttpRequest'},
    });
    return await r.json();
  }, {objectType, productID});
  assert(response.result === 'success', `syncGlobal endpoint ${objectType} succeeds`);
}

async function resetDefinition(page, objectType, productID) {
  await page.evaluate(async ({objectType, productID}) => {
    const fd = new FormData();
    fd.append('reset', '1');
    await fetch(`/index.php?m=statetransition&f=reset&objectType=${objectType}&productID=${productID}`, {
      method: 'POST',
      body: fd,
      headers: {'X-Requested-With': 'XMLHttpRequest'},
    });
  }, {objectType, productID});
}

async function verifyProductMatchesGlobal(page, objectType) {
  const global = await definitionFromManage(page, objectType, 0);
  const product = await definitionFromManage(page, objectType, PRODUCT_ID);
  assert(JSON.stringify(product.definition) === JSON.stringify(global.definition), `${objectType} product definition matches global`);
  assert((product.definition.entries || []).includes('g_e2e'), `${objectType} product uses global entry`);
  assert(!(product.definition.entries || []).includes('p_e2e'), `${objectType} product override was replaced`);
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try {
  const page = await (await browser.newContext()).newPage();
  page.on('dialog', dialog => dialog.accept());
  await login(page);

  for(const objectType of OBJECT_TYPES) {
    const baseDefinition = (await definitionFromManage(page, objectType, 0)).definition;
    await saveDefinition(page, objectType, 0, mutateDefinition(baseDefinition, 'g_e2e', `Global ${objectType}`));
    await saveDefinition(page, objectType, PRODUCT_ID, mutateDefinition(baseDefinition, 'p_e2e', `Product ${objectType}`));

    if(objectType === 'story') {
      await page.goto(`${BASE}/index.php?m=statetransition&f=browse&objectType=story&productID=${PRODUCT_ID}&_single=1`, {waitUntil: 'domcontentloaded', timeout: 60000});
      await page.waitForSelector('.statetransition-sync-global-btn', {timeout: 60000});
      assert(true, 'browse product page shows sync global button');
      await page.click('.statetransition-sync-global-btn');
      await page.waitForLoadState('domcontentloaded');
      assert(page.url().includes('m=statetransition'), 'browse sync click returns to statetransition');
    } else {
      await syncByEndpoint(page, objectType, PRODUCT_ID);
    }

    await verifyProductMatchesGlobal(page, objectType);
  }
  for(const objectType of OBJECT_TYPES) {
    await resetDefinition(page, objectType, 0);
    await resetDefinition(page, objectType, PRODUCT_ID);
  }
  console.log('✓ restored E2E workflow definitions');
} finally {
  await browser.close();
}

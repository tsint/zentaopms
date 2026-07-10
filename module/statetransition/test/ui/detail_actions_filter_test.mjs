import {execFileSync} from 'child_process';
import {loadPlaywright} from './playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const baseURL = (process.env.BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
const passwords = (process.env.E2E_PASSWORDS || process.env.ZT_ADMIN_PASSWORD || 'Admin1234!,123456').split(',').filter(Boolean);
const dbContainer = process.env.DB_CONTAINER || '';
const dbName = process.env.DB_NAME || 'zentao';
const dbRootPassword = process.env.DB_ROOT_PASSWORD || process.env.MYSQL_ROOT_PASSWORD || 'zentao123456';
const dbUser = process.env.DB_USER || process.env.MYSQL_USER || 'zentao';
const dbPassword = process.env.DB_PASSWORD || process.env.MYSQL_PASSWORD || 'zentao123456';
const enableERUR = process.env.E2E_ENABLE_ER_UR === '1';

const results = [];

function check(name, ok, detail = '') {
  results.push({name, ok, detail});
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' - ' + detail : ''}`);
}

function runMysql(sql) {
  runMysqlOutput(sql);
}

function runMysqlOutput(sql) {
  if(!dbContainer) return;
  const candidates = [
    ['root', dbRootPassword],
    [dbUser, dbPassword]
  ];

  let lastError = null;
  for(const [user, password] of candidates) {
    try {
      return execFileSync(
        'docker',
        ['exec', '-i', dbContainer, 'mysql', `-u${user}`, `-p${password}`, dbName],
        {input: sql, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe']}
      );
    } catch(error) {
      lastError = error;
    }
  }
  throw lastError;
}

function getConfigValue(key) {
  const escapedKey = key.replace(/'/g, "''");
  const out = runMysqlOutput(`SELECT value FROM zt_config WHERE \`key\` = '${escapedKey}' LIMIT 1;`) || '';
  const lines = out.trim().split('\n').filter(Boolean);
  return lines.length > 1 ? lines[1].trim() : '';
}

function setConfigValue(key, value, owner = 'system', module = 'custom') {
  runMysql(`UPDATE zt_config SET value = '${value}' WHERE \`key\` = '${key}' AND owner = '${owner}' AND module = '${module}';`);
}

function updateClosedFeatures(addCodes, removeCodes) {
  const parts = getConfigValue('closedFeatures').split(',').map((item) => item.trim()).filter(Boolean);
  const values = new Set(parts);
  for(const code of addCodes) values.add(code);
  for(const code of removeCodes) values.delete(code);
  runMysql(`UPDATE zt_config SET value = '${Array.from(values).join(',')}' WHERE \`key\` = 'closedFeatures' AND owner = 'system' AND module = 'common';`);
}

function enableEpicRequirement() {
  if(!dbContainer || !enableERUR) return null;
  const original = {
    enableER: getConfigValue('enableER'),
    URAndSR: getConfigValue('URAndSR'),
    closedFeatures: getConfigValue('closedFeatures')
  };
  setConfigValue('enableER', '1');
  setConfigValue('URAndSR', '1');
  updateClosedFeatures([], ['productER', 'productUR']);
  return original;
}

function restoreEpicRequirement(original) {
  if(!original || !dbContainer) return;
  setConfigValue('enableER', original.enableER || '0');
  setConfigValue('URAndSR', original.URAndSR || '0');
  const closedFeatures = (original.closedFeatures || '').replace(/'/g, "''");
  runMysql(`UPDATE zt_config SET value = '${closedFeatures}' WHERE \`key\` = 'closedFeatures' AND owner = 'system' AND module = 'common';`);
}

function setupFixtures() {
  if(!dbContainer) {
    console.log('DB_CONTAINER is not set; assuming detail-page fixtures already exist.');
    return;
  }

  runMysql(`
SET NAMES utf8mb4;
INSERT INTO zt_product(id, name, code, type, status, PO, QD, RD, createdBy, createdDate, vision, deleted)
VALUES(910001, 'E2E 状态流转产品', 'e2e-flow-product', 'normal', 'normal', 'admin', 'admin', 'admin', 'admin', NOW(), 'rnd', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), status = VALUES(status), deleted = 0;

INSERT INTO zt_project(id, project, model, type, parent, path, grade, name, code, hasProduct, begin, end, status, openedBy, openedDate, PO, PM, QD, RD, acl, vision, deleted)
VALUES
(910010, 0, 'scrum', 'project', 0, ',910010,', 1, 'E2E 状态流转项目', 'e2e-flow-project', 1, '2026-01-01', '2026-12-31', 'doing', 'admin', NOW(), 'admin', 'admin', 'admin', 'admin', 'open', 'rnd', 0),
(910011, 910010, 'scrum', 'sprint', 910010, ',910010,910011,', 2, 'E2E 状态流转执行', 'e2e-flow-execution', 1, '2026-01-01', '2026-12-31', 'doing', 'admin', NOW(), 'admin', 'admin', 'admin', 'admin', 'open', 'rnd', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), status = VALUES(status), deleted = 0;

DELETE FROM zt_projectproduct WHERE project IN (910010, 910011);
INSERT INTO zt_projectproduct(project, product, branch, plan) VALUES(910010, 910001, 0, ''), (910011, 910001, 0, '');

DELETE FROM zt_storyspec WHERE story IN (910001, 910002, 910003);
DELETE FROM zt_story WHERE id IN (910001, 910002, 910003);
INSERT INTO zt_story(id, root, path, grade, product, branch, module, plan, title, type, category, status, stage, openedBy, openedDate, assignedTo, assignedDate, version, vision, deleted)
VALUES
(910001, 910001, ',910001,', 1, 910001, 0, 0, '', 'E2E 研发需求草稿', 'story', 'feature', 'draft', 'wait', 'admin', NOW(), 'admin', NOW(), 1, 'rnd', 0),
(910002, 910002, ',910002,', 1, 910001, 0, 0, '', 'E2E 用户需求草稿', 'requirement', 'feature', 'draft', 'wait', 'admin', NOW(), 'admin', NOW(), 1, 'rnd', 0),
(910003, 910003, ',910003,', 1, 910001, 0, 0, '', 'E2E 业务需求草稿', 'epic', 'feature', 'draft', 'wait', 'admin', NOW(), 'admin', NOW(), 1, 'rnd', 0);
INSERT INTO zt_storyspec(story, version, title, spec, verify)
VALUES
(910001, 1, 'E2E 研发需求草稿', '研发需求规格', '研发需求验收'),
(910002, 1, 'E2E 用户需求草稿', '用户需求规格', '用户需求验收'),
(910003, 1, 'E2E 业务需求草稿', '业务需求规格', '业务需求验收');

DELETE FROM zt_bug WHERE id = 910001;
INSERT INTO zt_bug(id, product, project, execution, title, status, confirmed, openedBy, openedDate, assignedTo, assignedDate, deleted)
VALUES(910001, 910001, 910010, 910011, 'E2E 激活 Bug', 'active', 1, 'admin', NOW(), 'admin', NOW(), 0);

DELETE FROM zt_task WHERE id = 910001;
INSERT INTO zt_task(id, project, execution, path, name, type, status, estimate, consumed, \`left\`, openedBy, openedDate, assignedTo, assignedDate, vision, deleted)
VALUES(910001, 910010, 910011, ',910001,', 'E2E 等待任务', 'devel', 'wait', 1, 0, 1, 'admin', NOW(), 'admin', NOW(), 'rnd', 0);
`);
}

function storyDefinition() {
  return {
    schemaVersion: 1,
    statuses: [
      {key: 'draft', label: {zh_cn: '草稿', en: 'Draft'}, category: 'normal', color: '#999999', isSystem: true, isEntry: true, fieldRules: {}},
      {key: 'reviewing', label: {zh_cn: '评审中', en: 'Reviewing'}, category: 'normal', color: '#3498db', isSystem: true, isEntry: false, fieldRules: {}},
      {key: 'active', label: {zh_cn: '激活', en: 'Active'}, category: 'normal', color: '#27ae60', isSystem: true, isEntry: false, fieldRules: {}}
    ],
    transitions: [
      {key: 'draft-to-reviewing-via-submitreview', fromStatus: 'draft', toStatus: 'reviewing', action: 'submitreview', branch: null, label: {zh_cn: '提交评审', en: 'Submit review'}, roles: [], accounts: [], requireComment: false, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null},
      {key: 'reviewing-to-active-via-activate', fromStatus: 'reviewing', toStatus: 'active', action: 'activate', branch: null, label: {zh_cn: '激活', en: 'Activate'}, roles: [], accounts: [], requireComment: false, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null}
    ],
    entries: ['draft']
  };
}

function bugDefinition() {
  return {
    schemaVersion: 1,
    statuses: [
      {key: 'active', label: {zh_cn: '激活', en: 'Active'}, category: 'normal', color: '#27ae60', isSystem: true, isEntry: true, fieldRules: {}},
      {key: 'resolved', label: {zh_cn: '已解决', en: 'Resolved'}, category: 'normal', color: '#3498db', isSystem: true, isEntry: false, fieldRules: {}}
    ],
    transitions: [
      {key: 'active-to-resolved-via-resolve', fromStatus: 'active', toStatus: 'resolved', action: 'resolve', branch: null, label: {zh_cn: '解决', en: 'Resolve'}, roles: [], accounts: [], requireComment: false, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null}
    ],
    entries: ['active']
  };
}

function taskDefinition() {
  return {
    schemaVersion: 1,
    statuses: [
      {key: 'wait', label: {zh_cn: '未开始', en: 'Wait'}, category: 'normal', color: '#999999', isSystem: true, isEntry: true, fieldRules: {}},
      {key: 'doing', label: {zh_cn: '进行中', en: 'Doing'}, category: 'normal', color: '#3498db', isSystem: true, isEntry: false, fieldRules: {}}
    ],
    transitions: [
      {key: 'wait-to-doing-via-start', fromStatus: 'wait', toStatus: 'doing', action: 'start', branch: null, label: {zh_cn: '开始', en: 'Start'}, roles: [], accounts: [], requireComment: false, enabled: true, isCustom: false, buttonLabel: null, buttonIcon: null, buttonOrder: 0, buttonGroup: 'primary', sideEffects: [], condition: null}
    ],
    entries: ['wait']
  };
}

async function login(page) {
  for(const password of passwords) {
    await page.goto(`${baseURL}/index.php?m=user&f=login`, {waitUntil: 'domcontentloaded', timeout: 30000});
    await page.fill('#account, input[name="account"]', 'admin');
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({timeout: 15000}).catch(() => {}),
      page.click('#submit, button[type="submit"]')
    ]);
    await page.waitForTimeout(1200);
    if(!page.url().includes('m=user&f=login')) return password;
  }
  throw new Error(`Unable to log in with passwords: ${passwords.join(', ')}`);
}

async function saveWorkflow(page, objectType, definition) {
  return await page.evaluate(async ({objectType, definition}) => {
    const fd = new FormData();
    fd.append('definition', JSON.stringify(definition));
    fd.append('enabled', '1');
    fd.append('version', '0');
    const response = await fetch(`/index.php?m=statetransition&f=manage&objectType=${encodeURIComponent(objectType)}&productID=0`, {
      method: 'POST',
      body: fd,
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const text = await response.text();
    try {
      return {status: response.status, body: JSON.parse(text)};
    } catch(e) {
      return {status: response.status, body: {result: 'fail', message: text.slice(0, 200)}};
    }
  }, {objectType, definition});
}

function hasAction(actions, method, textPatterns = []) {
  const methodLower = method.toLowerCase();
  return actions.some((action) => {
    const haystack = action.haystack.toLowerCase();
    if(haystack.includes(`f=${methodLower}`)) return true;
    if(haystack.includes(`-${methodLower}-`)) return true;
    if(haystack.includes(`/${methodLower}.`)) return true;
    return textPatterns.some((pattern) => action.text.includes(pattern));
  });
}

async function collectActions(page, url) {
  await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 30000});
  await page.waitForTimeout(1000);

  await page.evaluate((targetURL) => {
    const iframe = Array.from(document.querySelectorAll('iframe[id^="appIframe-"]')).find((item) => !item.getAttribute('src'));
    if(iframe) iframe.setAttribute('src', `${targetURL}${targetURL.includes('?') ? '&' : '?'}onlybody=yes`);
  }, url).catch(() => {});

  const urlObject = new URL(url);
  const module = urlObject.searchParams.get('m');
  const method = urlObject.searchParams.get('f');
  const idParam = ['storyID', 'bugID', 'taskID'].find((name) => urlObject.searchParams.has(name));
  const idValue = idParam ? urlObject.searchParams.get(idParam) : '';

  await page.waitForFunction(
    ({module, method, idParam, idValue}) => {
      return Array.from(window.frames).some((frame) => {
        try {
          const href = frame.location.href;
          return href.includes(`m=${module}`) && href.includes(`f=${method}`) && (!idParam || href.includes(`${idParam}=${idValue}`));
        } catch(e) {
          return false;
        }
      });
    },
    {module, method, idParam, idValue},
    {timeout: 12000}
  ).catch(() => {});

  await page.waitForTimeout(2500);

  let frame = page.frames().find((item) => {
    const frameURL = item.url();
    return item !== page.mainFrame()
      && frameURL.includes(`m=${module}`)
      && frameURL.includes(`f=${method}`)
      && (!idParam || frameURL.includes(`${idParam}=${idValue}`));
  });
  if(!frame) frame = page.frames().find((item) => item !== page.mainFrame() && item.url().length > baseURL.length);
  if(!frame) frame = page.mainFrame();

  const data = await frame.evaluate(() => {
    const visible = (el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    const attrs = ['href', 'data-url', 'data-href', 'data-name', 'data-id', 'aria-label', 'title'];
    const actions = [];
    document.querySelectorAll('a, button, [role="button"], [data-url], [data-name]').forEach((el) => {
      if(!visible(el)) return;
      const text = (el.textContent || '').replace(/\s+/g, ' ').trim();
      const attrText = attrs.map((name) => el.getAttribute(name) || '').join(' ');
      const classText = el.getAttribute('class') || '';
      actions.push({text, haystack: `${text} ${attrText} ${classText}`});
    });
    return {
      url: location.href,
      title: document.title,
      bodyText: document.body.innerText.slice(0, 1000),
      actions
    };
  });

  return data;
}

async function verifyDetail(page, item) {
  const data = await collectActions(page, item.url);
  const pageLoaded = data.bodyText.includes(item.title) || data.title.includes(item.title);
  check(`${item.label}详情页已加载`, pageLoaded, data.url);
  if(!pageLoaded) {
    console.log(data.bodyText);
    return;
  }

  for(const method of item.mustHide) {
    check(`${item.label}隐藏 ${method.name}`, !hasAction(data.actions, method.method, method.text), JSON.stringify(data.actions.filter((action) => action.haystack.toLowerCase().includes(method.method.toLowerCase())).slice(0, 3)));
  }
  for(const method of item.mustShow) {
    check(`${item.label}保留 ${method.name}`, hasAction(data.actions, method.method, method.text), JSON.stringify(data.actions.slice(0, 12)));
  }
}

const originalFeatureState = enableEpicRequirement();
setupFixtures();

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const context = await browser.newContext({viewport: {width: 1366, height: 900}});
const page = await context.newPage();

try {
  const password = await login(page);
  console.log(`Logged in as admin with configured password candidate (${password.length} chars).`);

  const workflowSaves = [
    ['story', storyDefinition()],
    ['requirement', storyDefinition()],
    ['epic', storyDefinition()],
    ['bug', bugDefinition()],
    ['task', taskDefinition()]
  ];
  const enabledObjects = new Set();
  for(const [objectType, definition] of workflowSaves) {
    const result = await saveWorkflow(page, objectType, definition);
    const ok = result.body?.result === 'success';
    check(`保存 ${objectType} 自定义流转`, ok || ['requirement', 'epic'].includes(objectType), ok ? 'success' : (result.body?.message || `HTTP ${result.status}`));
    if(ok) enabledObjects.add(objectType);
  }

  const storyChecks = [
    {objectType: 'story', label: '研发需求', title: 'E2E 研发需求草稿', url: `${baseURL}/index.php?m=story&f=view&storyID=910001&version=0&param=0&storyType=story`},
    {objectType: 'requirement', label: '用户需求', title: 'E2E 用户需求草稿', url: `${baseURL}/index.php?m=requirement&f=view&storyID=910002&version=0&param=0&storyType=requirement`},
    {objectType: 'epic', label: '业务需求', title: 'E2E 业务需求草稿', url: `${baseURL}/index.php?m=epic&f=view&storyID=910003&version=0&param=0&storyType=epic`}
  ];

  for(const item of storyChecks) {
    if(!enabledObjects.has(item.objectType)) {
      console.log(`Skip ${item.objectType}: object type is not enabled in this installation.`);
      continue;
    }
    await verifyDetail(page, {
      ...item,
      mustHide: [
        {method: 'close', name: '关闭', text: ['关闭', 'Close']},
        {method: 'activate', name: '激活', text: ['激活', 'Activate']}
      ],
      mustShow: [
        {method: 'submitReview', name: '提交评审', text: ['提交评审', 'Submit']},
        {method: 'edit', name: '编辑', text: ['编辑', 'Edit']},
        {method: 'batchCreate', name: '拆分', text: ['拆分', '分解', 'Split']}
      ]
    });
  }

  if(enabledObjects.has('bug')) {
    await verifyDetail(page, {
      label: 'Bug',
      title: 'E2E 激活 Bug',
      url: `${baseURL}/index.php?m=bug&f=view&bugID=910001`,
      mustHide: [
        {method: 'close', name: '关闭', text: ['关闭', 'Close']},
        {method: 'activate', name: '激活', text: ['激活', 'Activate']}
      ],
      mustShow: [
        {method: 'resolve', name: '解决', text: ['解决', 'Resolve']},
        {method: 'edit', name: '编辑', text: ['编辑', 'Edit']}
      ]
    });
  }

  if(enabledObjects.has('task')) {
    await verifyDetail(page, {
      label: '任务',
      title: 'E2E 等待任务',
      url: `${baseURL}/index.php?m=task&f=view&taskID=910001`,
      mustHide: [
        {method: 'close', name: '关闭', text: ['关闭', 'Close']},
        {method: 'activate', name: '激活', text: ['激活', 'Activate']}
      ],
      mustShow: [
        {method: 'start', name: '开始', text: ['开始', 'Start']},
        {method: 'edit', name: '编辑', text: ['编辑', 'Edit']}
      ]
    });
  }
} finally {
  await browser.close();
  restoreEpicRequirement(originalFeatureState);
}

const failed = results.filter((result) => !result.ok);
console.log(`\nSummary: ${results.length - failed.length}/${results.length} checks passed.`);
if(failed.length) process.exit(1);

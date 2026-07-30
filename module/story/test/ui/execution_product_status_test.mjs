/**
 * End-to-end regression test for execution story browse with product workflow statuses.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 ZT_DB_HOST=172.19.0.2 ZT_DB_PASSWORD=zentao123456 node module/story/test/ui/execution_product_status_test.mjs
 */
import {spawnSync} from 'child_process';
import {loadPlaywright} from '../../../statetransition/test/ui/playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE      = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORDS = (process.env.E2E_PASSWORDS || 'Admin1234!,123456').split(',').filter(Boolean);
const DB_NAME   = process.env.ZT_DB_NAME || process.env.DB_NAME || 'zentao';
const DB_USER   = process.env.ZT_DB_USER || process.env.DB_USER || 'zentao';
const DB_PASS   = process.env.ZT_DB_PASSWORD || process.env.DB_PASSWORD || 'zentao123456';
const DB_HOST   = process.env.ZT_DB_HOST || process.env.DB_HOST || '127.0.0.1';
const DB_PORT   = process.env.ZT_DB_PORT || process.env.DB_PORT || '3306';
const DB_PREFIX = process.env.ZT_DB_PREFIX || process.env.DB_PREFIX || 'zt_';

const productID   = 1000001;
const projectID   = 1000000;
const executionID = 1000001;
const storyID     = 1000001;
const closedID    = 1000002;

function q(value)
{
    return `'${String(value).replace(/'/g, "''")}'`;
}

function runSQL(sql)
{
    const result = spawnSync('mysql', ['--protocol=TCP', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USER, '--default-character-set=utf8mb4', '-N', '-B', DB_NAME], {
        input: sql,
        encoding: 'utf8',
        env: {...process.env, MYSQL_PWD: DB_PASS}
    });
    if(result.status !== 0) throw new Error(result.stderr || result.stdout);
    return result.stdout;
}

function definition()
{
    return JSON.stringify({
        schemaVersion: 1,
        statuses: [
            {key: 'draft', label: {zh_cn: '草稿', en: 'Draft'}, category: 'normal', color: '#999999', isSystem: true, isEntry: true, fieldRules: {}},
            {key: 'reviewing', label: {zh_cn: '评审中', en: 'Reviewing'}, category: 'normal', color: '#3498db', isSystem: true, isEntry: false, fieldRules: {}},
            {key: 'active', label: {zh_cn: '激活', en: 'Active'}, category: 'normal', color: '#27ae60', isSystem: true, isEntry: true, fieldRules: {}},
            {key: 'changing', label: {zh_cn: '变更中', en: 'Changing'}, category: 'abnormal', color: '#f39c12', isSystem: true, isEntry: false, fieldRules: {}},
            {key: 'closed', label: {zh_cn: '已关闭', en: 'Closed'}, category: 'terminal', color: '#7f8c8d', isSystem: true, isEntry: false, fieldRules: {}},
            {key: 'prodopen', label: {zh_cn: '产品打开', en: 'Product Open'}, category: 'normal', color: '#1abc9c', isSystem: false, isEntry: false, fieldRules: {}}
        ],
        transitions: [],
        entries: ['draft', 'active']
    });
}

function seed()
{
    runSQL(`
DELETE FROM \`${DB_PREFIX}projectstory\` WHERE project = ${executionID};
DELETE FROM \`${DB_PREFIX}projectproduct\` WHERE project IN (${projectID}, ${executionID});
DELETE FROM \`${DB_PREFIX}story\` WHERE id IN (${storyID}, ${closedID});
DELETE FROM \`${DB_PREFIX}project\` WHERE id IN (${projectID}, ${executionID});
DELETE FROM \`${DB_PREFIX}product\` WHERE id = ${productID};
DELETE FROM \`${DB_PREFIX}workflow_definition\` WHERE objectType = 'story' AND productID IN (0, ${productID});

INSERT INTO \`${DB_PREFIX}product\` (\`id\`, \`name\`, \`code\`, \`status\`, \`type\`, \`createdBy\`, \`createdDate\`) VALUES
(${productID}, 'Product Status Test', 'product-status-test', 'normal', 'normal', 'admin', NOW());
INSERT INTO \`${DB_PREFIX}project\` (\`id\`, \`project\`, \`type\`, \`name\`, \`code\`, \`status\`, \`storyType\`, \`hasProduct\`, \`openedBy\`, \`openedDate\`) VALUES
(${projectID}, 0, 'project', 'Project Status Test', 'project-status-test', 'doing', 'story', 1, 'admin', NOW()),
(${executionID}, ${projectID}, 'sprint', 'Execution Status Test', 'execution-status-test', 'doing', 'story', 1, 'admin', NOW());
INSERT INTO \`${DB_PREFIX}projectproduct\` (\`project\`, \`product\`, \`branch\`) VALUES
(${projectID}, ${productID}, 0),
(${executionID}, ${productID}, 0);
INSERT INTO \`${DB_PREFIX}story\` (\`id\`, \`root\`, \`path\`, \`product\`, \`title\`, \`type\`, \`status\`, \`stage\`, \`openedBy\`, \`openedDate\`, \`deleted\`) VALUES
(${storyID}, ${storyID}, ',${storyID},', ${productID}, 'Product status story', 'story', 'prodopen', 'wait', 'admin', NOW(), 0),
(${closedID}, ${closedID}, ',${closedID},', ${productID}, 'Closed story', 'story', 'closed', 'wait', 'admin', NOW(), 0);
INSERT INTO \`${DB_PREFIX}projectstory\` (\`project\`, \`product\`, \`story\`, \`version\`, \`order\`) VALUES
(${executionID}, ${productID}, ${storyID}, 1, 1), (${executionID}, ${productID}, ${closedID}, 1, 2);
INSERT INTO \`${DB_PREFIX}workflow_definition\` (\`scope\`, \`productID\`, \`objectType\`, \`name\`, \`enabled\`, \`version\`, \`definition\`, \`createdBy\`, \`createdDate\`) VALUES
('product', ${productID}, 'story', 'Product workflow status test', '1', 1, ${q(definition())}, 'admin', NOW());
`);
}

async function login(page)
{
    for(const password of PASSWORDS)
    {
        await page.goto(`${BASE}/index.php?m=user&f=login`, {waitUntil: 'domcontentloaded', timeout: 30000});
        if(!await page.locator('#account').count()) return true;
        await page.fill('#account', 'admin');
        await page.fill('input[name="password"]', password);
        await Promise.all([
            page.waitForNavigation({timeout: 15000}).catch(() => {}),
            page.click('#submit')
        ]);
        await page.waitForTimeout(500);
        if(!page.url().includes('m=user') || !page.url().includes('f=login')) return true;
    }
    return false;
}

function check(ok, message)
{
    console.log(`${ok ? '✓' : '✗'} ${message}`);
    if(!ok) throw new Error(message);
}

seed();

const browser = await chromium.launch({headless: true});
const page = await browser.newPage();

try
{
    check(await login(page), 'Login as admin');
    await page.goto(`${BASE}/index.php?m=execution&f=story&executionID=${executionID}&storyType=story&orderBy=id_desc&type=byProduct&param=${productID}&_single=1`, {
        waitUntil: 'domcontentloaded',
        timeout: 30000
    });
    await page.waitForLoadState('networkidle', {timeout: 15000}).catch(() => {});

    const text = await page.locator('body').innerText({timeout: 10000});
    check(!/Internal Server Error|SQLSTATE|PDOException|You have an error in your SQL syntax|Call to/.test(text), 'Execution story page has no SQL/runtime error');
    check(text.includes('Product status story'), 'Execution story page renders product workflow story');
}
finally
{
    await browser.close();
}

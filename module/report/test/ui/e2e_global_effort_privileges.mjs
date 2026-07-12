/**
 * E2E test: 全局工时 follows group manageView/managePriv permissions.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 node module/report/test/ui/e2e_global_effort_privileges.mjs
 */
import {writeFileSync} from 'fs';
import {spawnSync} from 'child_process';
import {loadPlaywright} from '../../../statetransition/test/ui/playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE     = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const PASSWORD = process.env.E2E_PASSWORD || 'Admin1234!';

const DB_NAME = process.env.ZT_DB_NAME || process.env.DB_NAME || 'zentao';
const DB_USER = process.env.ZT_DB_USER || process.env.DB_USER || 'zentao';
const DB_PASS = process.env.ZT_DB_PASSWORD || process.env.DB_PASSWORD || 'zentao123456';
const DB_HOST = process.env.ZT_DB_HOST || process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.ZT_DB_PORT || process.env.DB_PORT || '3306';
const DB_PREFIX = process.env.ZT_DB_PREFIX || process.env.DB_PREFIX || 'zt_';

const users = {
    view:   {account: 'ge_view_only',   group: 9101, name: '全局工时仅查看'},
    export: {account: 'ge_export_csv',  group: 9102, name: '全局工时可导出'},
    none:   {account: 'ge_no_effort',   group: 9103, name: '全局工时无权限'}
};

const result = {steps: [], passed: 0, failed: 0};
function step(name, ok, detail = '')
{
    result.steps.push({name, ok, detail});
    if(ok) result.passed++; else result.failed++;
    console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

function runSQL(sql)
{
    const direct = spawnSync('mysql', ['--protocol=TCP', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USER, '--default-character-set=utf8mb4', DB_NAME], {
        input: sql,
        encoding: 'utf8',
        env: {...process.env, MYSQL_PWD: DB_PASS}
    });
    if(direct.status === 0) return;

    const compose = spawnSync('docker', ['compose', 'exec', '-T', 'db', 'mysql', `-u${DB_USER}`, `-p${DB_PASS}`, '--default-character-set=utf8mb4', DB_NAME], {
        input: sql,
        encoding: 'utf8'
    });
    if(compose.status === 0) return;

    throw new Error(`Cannot seed database. mysql: ${direct.stderr || direct.stdout}; docker compose: ${compose.stderr || compose.stdout}`);
}

function q(value)
{
    return `'${String(value).replace(/'/g, "''")}'`;
}

function seedGroupData()
{
    const passwordHash = '552b2ebe774bb5aaa0ad2021da259d22'; // md5(Admin1234!)
    const groupIDs = Object.values(users).map(user => user.group).join(',');
    const accounts = Object.values(users).map(user => q(user.account)).join(',');
    const sql = `
DELETE FROM \`${DB_PREFIX}grouppriv\` WHERE \`group\` IN (${groupIDs});
DELETE FROM \`${DB_PREFIX}usergroup\` WHERE \`group\` IN (${groupIDs}) OR account IN (${accounts});
DELETE FROM \`${DB_PREFIX}group\` WHERE id IN (${groupIDs});
DELETE FROM \`${DB_PREFIX}user\` WHERE account IN (${accounts});

INSERT INTO \`${DB_PREFIX}group\` (id, name, role, \`desc\`, developer, vision) VALUES
(${users.view.group}, ${q(users.view.name)}, '', '', 1, 'rnd'),
(${users.export.group}, ${q(users.export.name)}, '', '', 1, 'rnd'),
(${users.none.group}, ${q(users.none.name)}, '', '', 1, 'rnd');

INSERT INTO \`${DB_PREFIX}user\` (account, realname, password, gender, visions, deleted) VALUES
(${q(users.view.account)}, ${q(users.view.name)}, '${passwordHash}', 'f', 'rnd,lite', 0),
(${q(users.export.account)}, ${q(users.export.name)}, '${passwordHash}', 'f', 'rnd,lite', 0),
(${q(users.none.account)}, ${q(users.none.name)}, '${passwordHash}', 'f', 'rnd,lite', 0);

INSERT INTO \`${DB_PREFIX}usergroup\` (account, \`group\`) VALUES
(${q(users.view.account)}, ${users.view.group}),
(${q(users.export.account)}, ${users.export.group}),
(${q(users.none.account)}, ${users.none.group});

INSERT INTO \`${DB_PREFIX}grouppriv\` (\`group\`, module, method) VALUES
(${users.view.group}, 'my', 'index'),
(${users.export.group}, 'my', 'index'),
(${users.none.group}, 'my', 'index');
`;
    runSQL(sql);
}

async function login(context, account)
{
    const page = await context.newPage();
    await page.goto(`${BASE}/index.php`, {waitUntil: 'domcontentloaded', timeout: 20000});
    await page.waitForTimeout(800);

    if(await page.locator('#account').count())
    {
        await page.fill('#account', account);
        await page.fill('input[name="password"]', PASSWORD);
        await Promise.all([
            page.waitForNavigation({timeout: 20000}).catch(() => {}),
            page.click('#submit')
        ]);
        await page.waitForTimeout(1200);
    }
    return page;
}

async function postForm(page, url, fields)
{
    return await page.evaluate(async({url, fields}) =>
    {
        const body = new URLSearchParams();
        for(const [key, values] of Object.entries(fields))
        {
            for(const value of values) body.append(key, value);
        }

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        return {status: response.status, text: await response.text()};
    }, {url, fields});
}

async function configureGroup(page, user, allowBIView, methods)
{
    const viewFields = allowBIView ? {'actions[views][bi]': ['bi']} : {};
    const viewResult = await postForm(page, `${BASE}/index.php?m=group&f=manageView&groupID=${user.group}`, viewFields);
    if(viewResult.status !== 200) throw new Error(`manageView failed for ${user.group}: ${viewResult.status} ${viewResult.text}`);

    const privFields = {};
    if(methods.length) privFields['actions[screen][]'] = ['browse', 'view'];
    if(methods.length) privFields['actions[report][]'] = methods;
    const privResult = await postForm(page, `${BASE}/index.php?m=group&f=managePriv&type=byPackage&groupID=${user.group}&nav=bi`, privFields);
    if(privResult.status !== 200) throw new Error(`managePriv failed for ${user.group}: ${privResult.status} ${privResult.text}`);
}

async function clickHrefInAnyFrame(page, hrefPart)
{
    for(const frame of page.frames())
    {
        const locator = frame.locator(`a:visible[href*="${hrefPart}"]`).first();
        if(await locator.count())
        {
            await locator.click({timeout: 10000});
            return;
        }
    }
    throw new Error(`Cannot find clickable href containing: ${hrefPart}`);
}

async function globalEffortFrame(page)
{
    const deadline = Date.now() + 8000;
    while(Date.now() < deadline)
    {
        for(const frame of page.frames())
        {
            if(await frame.locator('#globalEffortPage').count()) return frame;
        }
        await page.waitForTimeout(250);
    }
    return null;
}

async function openGlobalEffort(page)
{
    await page.goto(`${BASE}/index.php?m=report&f=globalEffort`, {waitUntil: 'domcontentloaded', timeout: 20000});
    await page.waitForTimeout(1200);
    return await globalEffortFrame(page);
}

async function fetchCSVProbe(frame, url)
{
    return await frame.evaluate(async(href) =>
    {
        const response = await fetch(href, {credentials: 'include', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const bytes = Array.from(new Uint8Array(await response.arrayBuffer()));
        const text = new TextDecoder('utf-8').decode(new Uint8Array(bytes)).replace(/^\uFEFF/, '');
        return {
            status: response.status,
            contentType: response.headers.get('content-type') || '',
            firstBytes: bytes.slice(0, 3),
            firstLine: text.split(/\r?\n/)[0],
            hasCSVHeader: text.includes('来源,日期,产品,项目,执行,对象类型,对象ID,人员,耗时,剩余,工作内容')
        };
    }, url);
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try
{
    seedGroupData();
    step('Seed groups and users without direct global effort privileges', true);

    {
        const context = await browser.newContext({viewport: {width: 1280, height: 720}});
        const page = await login(context, 'admin');
        await configureGroup(page, users.view, true, ['globalEffort']);
        await configureGroup(page, users.export, true, ['globalEffort', 'exportGlobalEffortCSV']);
        await configureGroup(page, users.none, false, []);

        await page.goto(`${BASE}/index.php?m=group&f=manageView&groupID=${users.view.group}`, {waitUntil: 'domcontentloaded', timeout: 20000});
        await page.waitForTimeout(800);
        const adminFrame = page.frame({name: 'app-admin'}) || page.mainFrame();
        const viewHasBI = await adminFrame.locator('input[name="actions[views][bi]"]').count();
        step('manageView exposes BI visibility switch for the group', viewHasBI > 0, `count=${viewHasBI}`);

        await page.goto(`${BASE}/index.php?m=group&f=managepriv&type=byPackage&groupID=${users.export.group}&nav=bi`, {waitUntil: 'domcontentloaded', timeout: 20000});
        await page.waitForTimeout(1000);
        const privFrame = page.frame({name: 'app-admin'}) || page.mainFrame();
        const privText = await privFrame.locator('body').innerText();
        const privLinks = await privFrame.evaluate(() => Array.from(document.querySelectorAll('a[href*="m=group"][href*="f=managePriv"], a[href*="group-managePriv"]')).map(a => a.href).join('\n'));
        step('managePriv groupID URL shows 全局工时 permission package', privText.includes('全局工时') && privText.includes('查看全局工时') && privText.includes('导出全局工时'), privText.slice(0, 200));
        step('managePriv inner navigation keeps groupID parameter style', privLinks.includes(`groupID=${users.export.group}`) && !privLinks.includes(`param=${users.export.group}`), privLinks.slice(0, 300));
        await context.close();
    }

    {
        const context = await browser.newContext({viewport: {width: 1280, height: 720}});
        const page = await login(context, users.view.account);
        const frame = await openGlobalEffort(page);
        const exportButtonCount = frame ? await frame.locator('#exportGlobalEffortCSV').count() : -1;
        const text = frame ? await frame.locator('#globalEffortPage').innerText() : '';
        step('View-only user can open 全局工时', !!frame && text.includes('全局工时') && text.includes('整体分析'), `exportButtonCount=${exportButtonCount}`);
        step('View-only user cannot see CSV export button', exportButtonCount === 0, `exportButtonCount=${exportButtonCount}`);

        if(frame)
        {
            const queryURL = `${BASE}/index.php?m=report&f=globalEffort&export=csv&onlybody=yes`;
            const methodURL = `${BASE}/index.php?m=report&f=exportGlobalEffortCSV`;
            const queryProbe = await fetchCSVProbe(frame, queryURL);
            const methodProbe = await fetchCSVProbe(frame, methodURL);
            step('View-only user cannot export through globalEffort&export=csv', !queryProbe.hasCSVHeader, JSON.stringify(queryProbe));
            step('View-only user cannot export through exportGlobalEffortCSV', !methodProbe.hasCSVHeader, JSON.stringify(methodProbe));
        }
        await context.close();
    }

    {
        const context = await browser.newContext({viewport: {width: 1280, height: 720}});
        const page = await login(context, users.export.account);
        const frame = await openGlobalEffort(page);
        const href = frame ? await frame.locator('#exportGlobalEffortCSV').getAttribute('href') : '';
        const probe = frame && href ? await fetchCSVProbe(frame, href) : null;
        const ok = !!probe && probe.status === 200 && probe.firstBytes.join(',') === '239,187,191' && probe.hasCSVHeader;
        step('Export user sees CSV button and downloads readable CSV', ok, JSON.stringify(probe || {href}));
        await context.close();
    }

    {
        const context = await browser.newContext({viewport: {width: 1280, height: 720}});
        const page = await login(context, users.none.account);
        await clickHrefInAnyFrame(page, 'm=screen').catch(() => {});
        await page.waitForTimeout(1200);
        const menuInfo = await page.evaluate(() => document.body.innerText.includes('全局工时'));
        const frame = await openGlobalEffort(page);
        step('No-privilege user does not see 全局工时 in BI menu', menuInfo === false, `hasMenuText=${menuInfo}`);
        step('No-privilege user cannot render direct 全局工时 page', frame === null, `frame=${!!frame}, url=${page.url()}`);
        await context.close();
    }

    await browser.close();
}
catch(e)
{
    result.failed++;
    result.steps.push({name: 'exception', ok: false, detail: e.stack || e.message});
    console.error('EXCEPTION:', e.stack || e.message);
}

writeFileSync('/tmp/e2e_global_effort_privileges_result.json', JSON.stringify(result, null, 2));
console.log(`\nSummary: ${result.passed} passed, ${result.failed} failed`);
process.exit(result.failed === 0 ? 0 : 1);

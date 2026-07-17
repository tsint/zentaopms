/**
 * E2E test: 全局工时 correctly handles zt_effort.product comma-wrapped multi-product values.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 node module/report/test/ui/e2e_global_effort_multi_product.mjs
 */
import {writeFileSync} from 'fs';
import {spawnSync} from 'child_process';
import {loadPlaywright} from '../../../statetransition/test/ui/playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE     = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const ACCOUNT  = process.env.E2E_ACCOUNT || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || 'Admin1234!';

const DB_NAME   = process.env.ZT_DB_NAME || process.env.DB_NAME || 'zentao';
const DB_USER   = process.env.ZT_DB_USER || process.env.DB_USER || 'zentao';
const DB_PASS   = process.env.ZT_DB_PASSWORD || process.env.DB_PASSWORD || 'zentao123456';
const DB_HOST   = process.env.ZT_DB_HOST || process.env.DB_HOST || '127.0.0.1';
const DB_PORT   = process.env.ZT_DB_PORT || process.env.DB_PORT || '3306';
const DB_PREFIX = process.env.ZT_DB_PREFIX || process.env.DB_PREFIX || 'zt_';

const productA = 920001;
const productB = 920002;
const effortID = 920001;
const begin    = '2026-07-01';
const end      = '2026-07-31';

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

function seedMultiProductEffort()
{
    const sql = `
DELETE FROM \`${DB_PREFIX}effort\` WHERE id = ${effortID};
DELETE FROM \`${DB_PREFIX}product\` WHERE id IN (${productA}, ${productB});

INSERT INTO \`${DB_PREFIX}product\` (id, name, code, type, status, createdBy, createdDate, vision, deleted) VALUES
(${productA}, ${q('E2E 多产品 A')}, 'e2e-multi-product-a', 'normal', 'normal', 'admin', NOW(), 'rnd', 0),
(${productB}, ${q('E2E 多产品 B')}, 'e2e-multi-product-b', 'normal', 'normal', 'admin', NOW(), 'rnd', 0);

INSERT INTO \`${DB_PREFIX}effort\` (id, objectType, objectID, product, project, execution, account, work, date, consumed, \`left\`, vision, deleted) VALUES
(${effortID}, 'task', 920101, ',${productA},${productB},', 0, 0, 'admin', ${q('E2E multi product effort')}, '2026-07-15', 5.5, 1.5, 'rnd', 0);
`;
    runSQL(sql);
}

async function login(page)
{
    await page.goto(`${BASE}/index.php`, {waitUntil: 'domcontentloaded', timeout: 20000});
    await page.waitForTimeout(1000);

    if(!await page.locator('#account').count()) return;

    await page.fill('#account', ACCOUNT);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForNavigation({timeout: 20000}).catch(() => {}),
        page.click('#submit')
    ]);
    await page.waitForTimeout(1500);
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

async function openGlobalEffort(page, query)
{
    await page.goto(`${BASE}/index.php?m=report&f=globalEffort&${query}&zin=1`, {waitUntil: 'domcontentloaded', timeout: 30000});
    await page.waitForTimeout(1200);
    return await globalEffortFrame(page);
}

async function readCSV(frame)
{
    return await frame.evaluate(async() =>
    {
        const exportButton = document.querySelector('#exportGlobalEffortCSV');
        if(!exportButton) return {status: 0, text: 'No #exportGlobalEffortCSV button found'};

        const url      = exportButton.href;
        const response = await fetch(url, {credentials: 'include', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const bytes = new Uint8Array(await response.arrayBuffer());
        const text  = new TextDecoder('utf-8').decode(bytes).replace(/^\uFEFF/, '');
        return {status: response.status, text};
    });
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try
{
    seedMultiProductEffort();
    step('Seed multi-product effort record', true);

    const context = await browser.newContext({viewport: {width: 1280, height: 720}});
    const page = await context.newPage();
    await login(page);

    const query = `begin=${begin}&end=${end}&product=${productB}`;
    const frame = await openGlobalEffort(page, query);
    step('Open 全局工时 with product B filter', !!frame, frame ? frame.url() : 'No #globalEffortPage frame found');

    if(frame)
    {
        const info = await frame.evaluate(() =>
        {
            const text = document.querySelector('#globalEffortPage').innerText;
            const rows = Array.from(document.querySelectorAll('#globalEffortPage table tbody tr')).map(row => row.innerText.replace(/\s+/g, ' ').trim());
            const effortRow = rows.find(row => row.includes('920001,920002') && row.includes('920101') && row.includes('5.5') && row.includes('E2E multi product ef'));
            return {
                hasRecord: !!effortRow,
                hasProductPair: text.includes('920001,920002'),
                hasZeroProduct: !!effortRow && /(^|\s)0(\s|$)/.test(effortRow),
                rows
            };
        });

        step('Product B filter includes comma-wrapped zt_effort.product record', info.hasRecord, JSON.stringify(info.rows));
        step('Detail product cell preserves both product IDs instead of 0', info.hasProductPair && !info.hasZeroProduct, JSON.stringify(info));
    }

    if(frame)
    {
        const csv = await readCSV(frame);
        step('CSV export includes multi-product effort under product B filter', csv.status === 200 && csv.text.includes('E2E multi product effort'), csv.text.slice(0, 300));
        step('CSV export preserves product IDs instead of 0', csv.text.includes('920001,920002') && !csv.text.includes(',0,0,0,'), csv.text.slice(0, 300));
    }

    await context.close();
}
catch(error)
{
    step('Unexpected error', false, error.stack || String(error));
}
finally
{
    await browser.close();
    writeFileSync('/tmp/e2e_global_effort_multi_product_result.json', JSON.stringify(result, null, 2));
}

if(result.failed > 0) process.exit(1);

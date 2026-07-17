/**
 * E2E test: change password rejects unchanged passwords and clears IM remembered devices.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 ZT_DB_HOST=172.19.0.2 node module/user/test/ui/e2e_change_password_security.mjs
 */
import {createHash} from 'crypto';
import {writeFileSync} from 'fs';
import {spawnSync} from 'child_process';
import {loadPlaywright} from '../../../statetransition/test/ui/playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE        = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const ACCOUNT     = process.env.E2E_ACCOUNT || 'admin';
const OLD_PASS    = process.env.E2E_PASSWORD || 'Admin1234!';
const NEW_PASS    = process.env.E2E_NEW_PASSWORD || 'Codex123!';
const DB_NAME     = process.env.ZT_DB_NAME || process.env.DB_NAME || 'zentao';
const DB_USER     = process.env.ZT_DB_USER || process.env.DB_USER || 'zentao';
const DB_PASS     = process.env.ZT_DB_PASSWORD || process.env.DB_PASSWORD || 'zentao123456';
const DB_HOST     = process.env.ZT_DB_HOST || process.env.DB_HOST || '127.0.0.1';
const DB_PORT     = process.env.ZT_DB_PORT || process.env.DB_PORT || '3306';
const DB_PREFIX   = process.env.ZT_DB_PREFIX || process.env.DB_PREFIX || 'zt_';
const deviceTable = `${DB_PREFIX}im_userdevice`;

const result = {steps: [], passed: 0, failed: 0};
function step(name, ok, detail = '')
{
    result.steps.push({name, ok, detail});
    if(ok) result.passed++; else result.failed++;
    console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

function md5(value)
{
    return createHash('md5').update(value).digest('hex');
}

function q(value)
{
    return `'${String(value).replace(/'/g, "''")}'`;
}

function runSQL(sql)
{
    const direct = spawnSync('mysql', ['--protocol=TCP', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USER, '--default-character-set=utf8mb4', '-N', '-B', DB_NAME], {
        input: sql,
        encoding: 'utf8',
        env: {...process.env, MYSQL_PWD: DB_PASS}
    });
    if(direct.status === 0) return direct.stdout;

    const compose = spawnSync('docker', ['compose', 'exec', '-T', 'db', 'mysql', `-u${DB_USER}`, `-p${DB_PASS}`, '--default-character-set=utf8mb4', '-N', '-B', DB_NAME], {
        input: sql,
        encoding: 'utf8'
    });
    if(compose.status === 0) return compose.stdout;

    throw new Error(`Cannot run SQL. mysql: ${direct.stderr || direct.stdout}; docker compose: ${compose.stderr || compose.stdout}`);
}

function seedUserAndDevice()
{
    runSQL(`
UPDATE \`${DB_PREFIX}user\` SET password = ${q(md5(OLD_PASS))} WHERE account = ${q(ACCOUNT)};
DROP TABLE IF EXISTS \`${deviceTable}\`;
CREATE TABLE \`${deviceTable}\` (
  \`id\` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  \`user\` mediumint(8) NOT NULL DEFAULT 0,
  \`device\` char(40) NOT NULL DEFAULT 'default',
  \`deviceID\` char(40) NOT NULL DEFAULT '',
  \`token\` char(64) NOT NULL DEFAULT '',
  \`validUntil\` datetime DEFAULT NULL,
  \`lastLogin\` datetime DEFAULT NULL,
  \`lastLogout\` datetime DEFAULT NULL,
  \`online\` tinyint(1) NOT NULL DEFAULT 0,
  \`version\` char(10) NOT NULL DEFAULT '',
  PRIMARY KEY (\`id\`),
  UNIQUE KEY \`userdevice\` (\`user\`,\`device\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO \`${deviceTable}\` (\`user\`, \`device\`, \`token\`) VALUES (1, 'desktop', 'remembered-token');
`);
}

function cleanup()
{
    runSQL(`UPDATE \`${DB_PREFIX}user\` SET password = ${q(md5(OLD_PASS))} WHERE account = ${q(ACCOUNT)}; DROP TABLE IF EXISTS \`${deviceTable}\`;`);
}

function countDeviceRows()
{
    const output = runSQL(`SELECT COUNT(*) FROM \`${deviceTable}\` WHERE \`user\` = 1;`);
    const lines = output.trim().split(/\r?\n/).filter(Boolean);
    return Number(lines[lines.length - 1]);
}

async function login(page)
{
    await page.goto(`${BASE}/index.php`, {waitUntil: 'domcontentloaded', timeout: 30000});
    if(!await page.locator('#account').count()) return;

    await page.fill('#account', ACCOUNT);
    await page.fill('input[name="password"]', OLD_PASS);
    await Promise.all([
        page.waitForNavigation({timeout: 30000}).catch(() => {}),
        page.click('#submit')
    ]);
}

async function openChangePassword(page)
{
    await page.goto(`${BASE}/index.php?m=my&f=changePassword&onlybody=yes`, {waitUntil: 'domcontentloaded', timeout: 30000});
    await page.waitForSelector('input[name="originalPassword"]', {timeout: 10000});
}

async function submitChangePassword(page, currentPassword, newPassword)
{
    await openChangePassword(page);
    await page.fill('input[name="originalPassword"]', currentPassword);
    await page.fill('input[name="password1"]', newPassword);
    await page.fill('input[name="password2"]', newPassword);

    const responsePromise = page.waitForResponse(response => response.url().includes('m=my') && response.url().includes('f=changePassword'), {timeout: 15000}).catch(() => null);
    await page.click('button[type="submit"]');
    const response = await responsePromise;
    const text = response ? await response.text() : await page.locator('body').innerText();
    return text;
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try
{
    seedUserAndDevice();
    let deviceRows = countDeviceRows();
    step('Seed admin password and IM remembered device', deviceRows === 1, String(deviceRows));

    const context = await browser.newContext({viewport: {width: 1280, height: 720}});
    const page = await context.newPage();
    await login(page);

    const sameText = await submitChangePassword(page, OLD_PASS, OLD_PASS);
    step('Same plaintext password is rejected through UI flow', sameText.includes('新密码不能与原密码相同') || sameText.includes('same as the current password'), sameText.slice(0, 300));
    deviceRows = countDeviceRows();
    step('Rejected password change keeps IM remembered device', deviceRows === 1, String(deviceRows));

    const changeText = await submitChangePassword(page, OLD_PASS, NEW_PASS);
    step('Different password change succeeds through UI flow', changeText.includes('success') || changeText.includes('保存成功'), changeText.slice(0, 300));
    deviceRows = countDeviceRows();
    step('Successful password change clears IM remembered device', deviceRows === 0, String(deviceRows));

    await context.close();
}
catch(error)
{
    step('Unexpected error', false, error.stack || String(error));
}
finally
{
    cleanup();
    await browser.close();
    writeFileSync('/tmp/e2e_change_password_security_result.json', JSON.stringify(result, null, 2));
}

if(result.failed > 0) process.exit(1);

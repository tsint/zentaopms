/**
 * Comprehensive toggle-entry + save + reload cycle test.
 * Tests both global (productID=0) and per-product (productID=4) scopes.
 *
 * For each scope:
 *   - Reset to default
 *   - Toggle 'active' OFF (remove from entries)
 *   - Save
 *   - Reload, verify active NOT in entries
 *   - Toggle 'reviewing' ON (add to entries)
 *   - Save
 *   - Reload, verify reviewing IN entries
 *   - Reset
 */
import { chromium } from 'playwright';

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();

await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(3000);

async function readEntries(pid) {
    await page.goto(`http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=${pid}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(12000);
    const iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
    return await iframe.evaluate(() => {
        const root = document.getElementById('workflowEditor');
        if (!root) return ['ERROR_NO_EDITOR'];
        return JSON.parse(root.dataset.definition).entries;
    });
}

async function toggleAndSave(pid, statusKey, action) {
    await page.goto(`http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=${pid}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(8000);
    const iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();

    // Find the toggle button
    const toggleResult = await iframe.evaluate((key) => {
        const btn = document.querySelector(`.workflow-column[data-status="${key}"] .workflow-entry-toggle`);
        if (!btn) return 'no button';
        btn.click();
        return btn.classList.contains('is-entry') ? 'now-entry' : 'now-not-entry';
    }, statusKey);
    await page.waitForTimeout(1000);

    // Save
    const saveResult = await iframe.evaluate(async () => {
        const root = document.getElementById('workflowEditor');
        const def = JSON.parse(root.dataset.definition);
        const fd = new FormData();
        fd.append('definition', JSON.stringify(def));
        fd.append('enabled', '1');
        fd.append('version', root.dataset.version);
        const r = await fetch(root.dataset.saveUrl, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return await r.json();
    });
    return { toggleResult, saveResult };
}

async function resetScope(pid) {
    const result = await page.evaluate(async (p) => {
        const fd = new FormData();
        fd.append('confirm', '1');
        const r = await fetch(`/index.php?m=statetransition&f=reset&objectType=story&productID=${p}`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const text = await r.text();
        return { status: r.status, body: text.substring(0, 200) };
    }, pid);
    console.log(`[reset ${pid}] response:`, JSON.stringify(result));
    await page.waitForTimeout(1000);
}

for (const pid of [0, 4]) {
    console.log(`\n========== Scope productID=${pid} ==========`);

    // Reset
    await resetScope(pid);
    let entries = await readEntries(pid);
    console.log('Initial:', entries);
    check(`[${pid}] Reset to default has draft+active entries`, entries.includes('draft') && entries.includes('active'), JSON.stringify(entries));

    // Test 1: Toggle active OFF
    console.log('\n--- Test 1: Toggle active OFF ---');
    let r1 = await toggleAndSave(pid, 'active', 'off');
    console.log('Toggle:', r1.toggleResult, 'Save:', r1.saveResult.result, r1.saveResult.message);
    check(`[${pid}] Save after toggle active off succeeds`, r1.saveResult.result === 'success', r1.saveResult.message);

    entries = await readEntries(pid);
    console.log('After toggle off+save:', entries);
    check(`[${pid}] active removed from entries after toggle off`, !entries.includes('active'), JSON.stringify(entries));
    check(`[${pid}] draft still in entries`, entries.includes('draft'), JSON.stringify(entries));

    // Test 2: Toggle reviewing ON
    console.log('\n--- Test 2: Toggle reviewing ON ---');
    let r2 = await toggleAndSave(pid, 'reviewing', 'on');
    console.log('Toggle:', r2.toggleResult, 'Save:', r2.saveResult.result, r2.saveResult.message);
    check(`[${pid}] Save after toggle reviewing on succeeds`, r2.saveResult.result === 'success', r2.saveResult.message);

    entries = await readEntries(pid);
    console.log('After toggle on+save:', entries);
    check(`[${pid}] reviewing added to entries`, entries.includes('reviewing'), JSON.stringify(entries));

    // Reset for cleanup
    await resetScope(pid);
}

console.log(`\n========= Summary: ${results.filter(r => r.ok).length} / ${results.length} =========`);
await browser.close();
process.exit(results.every(r => r.ok) ? 0 : 1);

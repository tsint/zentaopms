/**
 * Comprehensive E2E test covering all functional scenarios.
 * Tests the complete user journey: add status, add transition, edit, save, reload,
 * delete, reset — for both global (productID=0) and product scope (productID=4).
 */
import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok });
  console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('console', msg => console.log('[CONSOLE]', msg.type(), msg.text().substring(0, 200)));
page.on('pageerror', err => console.log('[PAGE ERROR]', err.message.substring(0, 200)));

// Login
await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(3000);
check('Login', !page.url().includes('user/login'), '');

async function loadManage(pid) {
    await page.goto(`http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=${pid}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(10000);
    return page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
}

async function readState(iframe) {
    return await iframe.evaluate(() => {
        const root = document.getElementById('workflowEditor');
        if (!root) return null;
        const def = JSON.parse(root.dataset.definition);
        return {
            statuses: def.statuses.map(s => s.key),
            transitions: def.transitions.map(t => ({ key: t.key, from: t.fromStatus, to: t.toStatus, action: t.action, enabled: t.enabled, requireComment: t.requireComment })),
            entries: def.entries,
            version: root.dataset.version,
            matrixColumns: document.querySelectorAll('#workflowBoard .workflow-column').length,
            listTransitions: document.querySelectorAll('#workflowList .workflow-transition').length,
            svgPathCount: document.querySelectorAll('#workflowMermaid svg g.edgePaths > path').length,
            sourceDropdown: Array.from(document.getElementById('newSource')?.options || []).map(o => o.value),
            targetDropdown: Array.from(document.getElementById('newTarget')?.options || []).map(o => o.value)
        };
    });
}

async function resetScope(pid) {
    await page.evaluate(async (p) => {
        const fd = new FormData();
        fd.append('confirm', '1');
        await fetch(`/index.php?m=statetransition&f=reset&objectType=story&productID=${p}`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }, pid);
    await page.waitForTimeout(1000);
}

for (const pid of [0, 4]) {
    console.log(`\n========== Scope productID=${pid} ==========`);

    // Reset
    await resetScope(pid);

    // === Test 1: Add custom status ===
    console.log('\n--- Test 1: Add custom status "blocked" ---');
    let iframe = await loadManage(pid);
    await iframe.evaluate(() => {
        document.getElementById('newNodeKey').value = 'blocked';
        document.getElementById('newNodeLabel').value = '阻塞';
        document.getElementById('addNode').click();
    });
    await page.waitForTimeout(2500);
    iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
    let s = await readState(iframe);
    check(`[${pid}] Custom status added to definition`, s.statuses.includes('blocked'), JSON.stringify(s.statuses));
    check(`[${pid}] Custom status in matrix`, s.matrixColumns === 6, `cols=${s.matrixColumns}`);
    check(`[${pid}] Custom status in dropdowns`, s.sourceDropdown.includes('blocked') && s.targetDropdown.includes('blocked'), '');
    check(`[${pid}] Custom status in mermaid SVG`, s.svgPathCount >= 9, `paths=${s.svgPathCount}`);

    // === Test 2: Add transition using custom status ===
    console.log('\n--- Test 2: Add transition active→blocked ---');
    await iframe.evaluate(() => {
        const sourceSel = document.getElementById('newSource');
        sourceSel.value = 'active';
        sourceSel.dispatchEvent(new Event('change', { bubbles: true }));
        const targetSel = document.getElementById('newTarget');
        targetSel.value = 'blocked';
        targetSel.dispatchEvent(new Event('change', { bubbles: true }));
        const actionSel = document.getElementById('newAction');
        actionSel.value = actionSel.options[actionSel.selectedIndex].value;
        document.getElementById('addTransition').click();
    });
    await page.waitForTimeout(2000);
    iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
    s = await readState(iframe);
    const hasNewTrans = s.transitions.some(t => t.from === 'active' && t.to === 'blocked');
    check(`[${pid}] Transition active→blocked added`, hasNewTrans, JSON.stringify(s.transitions.filter(t => t.to === 'blocked')));
    check(`[${pid}] Transition count increased`, s.listTransitions === 10, `count=${s.listTransitions}`);

    // === Test 3: Save and reload ===
    console.log('\n--- Test 3: Save and reload ---');
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
    check(`[${pid}] Save returns success`, saveResult.result === 'success', saveResult.message);

    // Reload and verify persistence
    iframe = await loadManage(pid);
    s = await readState(iframe);
    check(`[${pid}] Custom status persisted after reload`, s.statuses.includes('blocked'), JSON.stringify(s.statuses));
    const transPersisted = s.transitions.some(t => t.from === 'active' && t.to === 'blocked');
    check(`[${pid}] Custom transition persisted after reload`, transPersisted, '');

    // === Test 4: Delete transition via UI ===
    console.log('\n--- Test 4: Delete transition ---');
    // Select the active→blocked transition via mermaid click
    await iframe.evaluate(() => {
        // Find the edge with to=blocked
        const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
        for (const p of paths) {
            const key = p.getAttribute('data-edge-key');
            if (key && key.includes('blocked')) {
                p.dispatchEvent(new MouseEvent('click', { bubbles: true }));
                return;
            }
        }
    });
    await page.waitForTimeout(1000);
    // Click delete button
    await iframe.evaluate(() => {
        const btn = document.getElementById('deleteTransition');
        if (btn) {
            // Override confirm
            window.confirm = () => true;
            btn.click();
        }
    });
    await page.waitForTimeout(1500);
    iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
    s = await readState(iframe);
    const transDeleted = !s.transitions.some(t => t.to === 'blocked');
    check(`[${pid}] Transition deleted`, transDeleted, `transitions=${s.transitions.length}`);

    // === Test 5: Delete custom status ===
    console.log('\n--- Test 5: Delete custom status "blocked" ---');
    await iframe.evaluate(() => {
        window.confirm = () => true;
        const btn = document.querySelector('.workflow-column[data-status="blocked"] .workflow-node-delete');
        if (btn) btn.click();
    });
    await page.waitForTimeout(1500);
    iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();
    s = await readState(iframe);
    check(`[${pid}] Status deleted`, !s.statuses.includes('blocked'), JSON.stringify(s.statuses));
    check(`[${pid}] Status removed from dropdowns`, !s.sourceDropdown.includes('blocked'), '');

    // === Test 6: Edit transition properties ===
    console.log('\n--- Test 6: Edit transition (requireComment) ---');
    // Find and click first transition
    await iframe.evaluate(() => {
        const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
        if (paths.length > 0) paths[0].dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    await page.waitForTimeout(1000);
    // Toggle requireComment
    const beforeReqComment = await iframe.evaluate(() => document.getElementById('edgeRequireComment')?.checked);
    await iframe.evaluate(() => {
        const chk = document.getElementById('edgeRequireComment');
        if (chk) { chk.checked = !chk.checked; chk.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    await page.waitForTimeout(500);
    // Save
    const editSaveResult = await iframe.evaluate(async () => {
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
    check(`[${pid}] Edit + save succeeds`, editSaveResult.result === 'success', editSaveResult.message);

    // Reload and verify
    iframe = await loadManage(pid);
    s = await readState(iframe);
    const editedTrans = s.transitions[0];
    const afterReqComment = editedTrans?.requireComment;
    check(`[${pid}] requireComment change persisted`, beforeReqComment !== afterReqComment, `before=${beforeReqComment} after=${afterReqComment}`);

    // === Test 7: Reset to default ===
    console.log('\n--- Test 7: Reset to default ---');
    await resetScope(pid);
    iframe = await loadManage(pid);
    s = await readState(iframe);
    check(`[${pid}] Reset restores 5 default statuses`, s.statuses.length === 5, `count=${s.statuses.length}`);
    check(`[${pid}] Reset restores 9 default transitions`, s.transitions.length === 9, `count=${s.transitions.length}`);
    check(`[${pid}] Reset restores default entries`, JSON.stringify(s.entries.sort()) === JSON.stringify(['active', 'draft'].sort()), JSON.stringify(s.entries));
}

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n========= Summary: ${passed} / ${results.length} passed, ${failed} failed =========`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

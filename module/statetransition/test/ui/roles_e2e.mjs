/**
 * E2E: set roles restriction on a transition, verify it's enforced on story detail page.
 *
 * Flow:
 *   1. Reset workflow
 *   2. Go to manage page
 *   3. Click a transition (e.g., change: active→changing)
 *   4. Select only 'dev' in the roles multi-select
 *   5. Save
 *   6. Reload manage, verify roles=['dev'] persisted
 *   7. Open story detail (admin user, role='')
 *   8. Verify 'change' button is HIDDEN (admin has no dev role)
 *   9. Reset workflow
 *   10. Open story detail again
 *   11. Verify 'change' button is VISIBLE again (no role restriction)
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
check('Login', !page.url().includes('user/login'), '');

// Ensure story 1 is active
await page.evaluate(async () => {
    await fetch('/index.php?m=story&f=activate&storyID=1', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} }).catch(() => {});
});

// Reset workflow
console.log('\n=== Reset workflow ===');
await page.evaluate(async () => {
    const fd = new FormData(); fd.append('confirm', '1');
    await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
});
await page.waitForTimeout(1000);

// === Step 1: Go to manage, select transition, set roles=['dev'] ===
console.log('\n=== Step 1: Set roles=[dev] on change transition ===');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(12000);
let iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();

// Find and click the 'change' transition edge
const edgeClicked = await iframe.evaluate(() => {
    const paths = document.querySelectorAll('g.edgePaths > path[data-edge-key]');
    for (const p of paths) {
        const key = p.getAttribute('data-edge-key');
        if (key && key.includes('change')) {
            p.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            return key;
        }
    }
    return null;
});
console.log('Clicked edge:', edgeClicked);
await page.waitForTimeout(1500);

// Check panel is visible
const panelVisible = await iframe.evaluate(() => {
    const e = document.getElementById('ruleEditor');
    return e ? !e.classList.contains('hidden') : false;
});
check('Edit panel visible after clicking change edge', panelVisible, '');

// Select ONLY 'dev' in the roles multi-select
if (panelVisible) {
    const rolesResult = await iframe.evaluate(() => {
        const sel = document.getElementById('edgeRoles');
        if (!sel) return 'no select';
        // Deselect all
        Array.from(sel.options).forEach(o => o.selected = false);
        // Select only 'dev'
        const devOpt = Array.from(sel.options).find(o => o.value === 'dev');
        if (devOpt) devOpt.selected = true;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        return {
            selectedCount: sel.selectedOptions.length,
            selectedValues: Array.from(sel.selectedOptions).map(o => o.value)
        };
    });
    console.log('Roles selection:', JSON.stringify(rolesResult));
    check('Selected dev role only', rolesResult.selectedValues?.length === 1 && rolesResult.selectedValues[0] === 'dev', JSON.stringify(rolesResult.selectedValues));
}

// === Step 2: Save ===
console.log('\n=== Step 2: Save ===');
const saveResult = await iframe.evaluate(async () => {
    const root = document.getElementById('workflowEditor');
    const def = JSON.parse(root.dataset.definition);
    const fd = new FormData();
    fd.append('definition', JSON.stringify(def));
    fd.append('enabled', '1');
    fd.append('version', root.dataset.version);
    const r = await fetch(root.dataset.saveUrl, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
    return await r.json();
});
check('Save succeeds', saveResult.result === 'success', saveResult.message);

// === Step 3: Reload manage, verify roles persisted ===
console.log('\n=== Step 3: Verify roles persisted ===');
await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(12000);
iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();

const savedRoles = await iframe.evaluate(() => {
    const root = document.getElementById('workflowEditor');
    const def = JSON.parse(root.dataset.definition);
    const tr = def.transitions.find(t => t.action === 'change' && t.fromStatus === 'active');
    return tr ? tr.roles : 'transition not found';
});
check('Roles=[dev] persisted after reload', JSON.stringify(savedRoles) === '["dev"]', JSON.stringify(savedRoles));

// === Step 4: Open story detail, verify change button hidden ===
console.log('\n=== Step 4: Story detail — change button should be HIDDEN (admin has no dev role) ===');
await page.goto('http://127.0.0.1:8080/index.php?m=story&f=view&storyID=1', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(12000);

let frame = page.frames().find(f => f !== page.mainFrame() && f.url().includes('story')) || page.mainFrame();
const storyButtons = await frame.evaluate(() => {
    const els = document.querySelectorAll('a, button');
    const found = {};
    els.forEach(el => {
        const text = (el.textContent || '').trim().substring(0, 20);
        const visible = el.offsetHeight > 0;
        const href = el.getAttribute('href') || '';
        if(href.includes('story-change') || text === '变更') {
            found['change'] = { visible, href: href.substring(0, 60) };
        }
        if(href.includes('story-close') || text === '关闭') {
            found['close'] = { visible };
        }
    });
    return found;
}).catch(e => ({ error: e.message }));

console.log('Story buttons:', JSON.stringify(storyButtons, null, 2));
check('Change button HIDDEN (admin is not dev)', !storyButtons.change?.visible, `changeVisible=${storyButtons.change?.visible}`);

// === Step 5: Reset and verify change button reappears ===
console.log('\n=== Step 5: Reset, verify change button reappears ===');
await page.evaluate(async () => {
    const fd = new FormData(); fd.append('confirm', '1');
    await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
});
await page.waitForTimeout(1000);

await page.goto('http://127.0.0.1:8080/index.php?m=story&f=view&storyID=1', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(12000);
frame = page.frames().find(f => f !== page.mainFrame() && f.url().includes('story')) || page.mainFrame();

const afterReset = await frame.evaluate(() => {
    const els = document.querySelectorAll('a, button');
    const found = {};
    els.forEach(el => {
        const text = (el.textContent || '').trim().substring(0, 20);
        const visible = el.offsetHeight > 0;
        if(el.getAttribute('href')?.includes('story-change') || text === '变更') {
            found['change'] = { visible };
        }
    });
    return found;
}).catch(() => ({}));

check('After reset: change button VISIBLE again', afterReset.change?.visible === true, `changeVisible=${afterReset.change?.visible}`);

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n=== Summary: ${passed} / ${results.length} passed, ${failed} failed ===`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

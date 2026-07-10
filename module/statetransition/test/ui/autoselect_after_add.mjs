import {loadPlaywright} from './playwright-loader.mjs';

const { chromium } = await loadPlaywright();
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
page.on('console', msg => console.log('[C]', msg.text().substring(0, 300)));

await page.goto('http://127.0.0.1:8080/index.php?m=user&f=login', { waitUntil: 'domcontentloaded' });
await page.fill('#account', 'admin');
await page.fill('input[name="password"]', '123456');
await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), page.click('#submit')]);
await page.waitForTimeout(3000);

await page.evaluate(async () => {
    const fd = new FormData(); fd.append('confirm', '1');
    await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
});
await page.waitForTimeout(1000);

await page.goto('http://127.0.0.1:8080/index.php?m=statetransition&f=manage&objectType=story&productID=0', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(12000);
const iframe = page.frames().find(f => f.name() === 'app-admin') || page.mainFrame();

// Use a VALID combo: closed→reviewing via submitreview (no existing closed+submitreview in default)
console.log('=== Add closed→reviewing via submitreview ===');
await iframe.evaluate(() => {
    document.getElementById('newSource').value = 'closed';
    document.getElementById('newTarget').value = 'reviewing';
    // Find submitreview action
    const actionSel = document.getElementById('newAction');
    actionSel.value = 'submitreview';
    document.getElementById('addTransition').click();
});
await page.waitForTimeout(3000);

const state = await iframe.evaluate(() => {
    const root = document.getElementById('workflowEditor');
    const def = JSON.parse(root.dataset.definition);
    return {
        transCount: def.transitions.length,
        lastTrans: def.transitions[def.transitions.length-1]?.key,
        panelVisible: !document.getElementById('ruleEditor')?.classList.contains('hidden'),
        rolesVisible: document.getElementById('edgeRoles')?.offsetHeight > 0,
        selectedEdge: document.querySelector('[data-edge-key].is-selected')?.getAttribute('data-edge-key')
    };
});
console.log('State:', JSON.stringify(state, null, 2));
const pass = state.transCount === 10 && state.panelVisible && state.rolesVisible;
console.log(`\nResult: ${pass ? 'PASS ✓' : 'FAIL ✗'}`);

await browser.close();
process.exit(pass ? 0 : 1);

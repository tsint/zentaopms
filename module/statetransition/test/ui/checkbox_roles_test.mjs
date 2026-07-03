import { chromium } from 'playwright';
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
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

// Test 1: Check add form has roles checkboxes
const addForm = await iframe.evaluate(() => {
    return {
        newRolesCheckboxes: document.querySelectorAll('input[name="newRoles"]').length,
        newRequireCommentExists: !!document.getElementById('newRequireComment')
    };
});
console.log('Add form:', JSON.stringify(addForm));

// Test 2: Add transition with roles
await iframe.evaluate(() => {
    document.getElementById('newSource').value = 'closed';
    document.getElementById('newTarget').value = 'reviewing';
    document.getElementById('newAction').value = 'submitreview';
    // Check 'dev' role
    const devCb = document.querySelector('input[name="newRoles"][value="dev"]');
    if(devCb) devCb.checked = true;
    // Check requireComment
    const reqCb = document.getElementById('newRequireComment');
    if(reqCb) reqCb.checked = true;
    document.getElementById('addTransition').click();
});
await page.waitForTimeout(2500);

const result = await iframe.evaluate(() => {
    const root = document.getElementById('workflowEditor');
    const def = JSON.parse(root.dataset.definition);
    const tr = def.transitions.find(t => t.key === 'closed-to-reviewing-via-submitreview');
    return {
        transFound: !!tr,
        roles: tr?.roles,
        requireComment: tr?.requireComment,
        panelVisible: !document.getElementById('ruleEditor')?.classList.contains('hidden'),
        editPanelRolesCheckboxes: document.querySelectorAll('input[name="edgeRoles"]').length,
        editRolesDevChecked: document.querySelector('input[name="edgeRoles"][value="dev"]')?.checked
    };
});
console.log('Result:', JSON.stringify(result, null, 2));

const pass = result.transFound && result.roles?.length === 1 && result.roles[0] === 'dev' &&
             result.requireComment === true && result.panelVisible && result.editPanelRolesCheckboxes > 0;
console.log(`\nTest: ${pass ? 'PASS ✓' : 'FAIL ✗'}`);
await browser.close();
process.exit(pass ? 0 : 1);

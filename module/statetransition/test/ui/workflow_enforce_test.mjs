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

// === Setup strict workflow (no close, no activate from active) ===
console.log('=== Setup strict workflow ===');
const strictDef = {
    schemaVersion: 1,
    statuses: [
        {key:'draft',label:{zh_cn:'草稿',en:'Draft'},category:'normal',color:'#999999',isSystem:true,isEntry:true,fieldRules:{}},
        {key:'reviewing',label:{zh_cn:'评审中',en:'Reviewing'},category:'normal',color:'#3498db',isSystem:true,isEntry:false,fieldRules:{}},
        {key:'active',label:{zh_cn:'激活',en:'Active'},category:'normal',color:'#27ae60',isSystem:true,isEntry:true,fieldRules:{}},
        {key:'changing',label:{zh_cn:'变更中',en:'Changing'},category:'abnormal',color:'#f39c12',isSystem:true,isEntry:false,fieldRules:{}},
        {key:'closed',label:{zh_cn:'已关闭',en:'Closed'},category:'terminal',color:'#7f8c8d',isSystem:true,isEntry:false,fieldRules:{}}
    ],
    transitions: [
        {key:'draft-to-reviewing-via-submitreview',fromStatus:'draft',toStatus:'reviewing',action:'submitreview',branch:null,label:{zh_cn:'提交评审',en:'Submit'},roles:[],accounts:[],requireComment:false,enabled:true,isCustom:false,buttonLabel:null,buttonIcon:null,buttonOrder:0,buttonGroup:'primary',sideEffects:[],condition:null},
        {key:'reviewing-to-active-via-review-pass',fromStatus:'reviewing',toStatus:'active',action:'review',branch:'pass',label:{zh_cn:'通过',en:'Pass'},roles:[],accounts:[],requireComment:false,enabled:true,isCustom:false,buttonLabel:null,buttonIcon:null,buttonOrder:0,buttonGroup:'primary',sideEffects:[],condition:null},
        // ONLY change from active. NO close, NO activate from active.
        {key:'active-to-changing-via-change',fromStatus:'active',toStatus:'changing',action:'change',branch:null,label:{zh_cn:'变更',en:'Change'},roles:[],accounts:[],requireComment:true,enabled:true,isCustom:false,buttonLabel:null,buttonIcon:null,buttonOrder:0,buttonGroup:'primary',sideEffects:[],condition:null}
    ],
    entries: ['draft', 'active']
};

const saveResult = await page.evaluate(async (def) => {
    const fd = new FormData();
    fd.append('definition', JSON.stringify(def));
    fd.append('enabled', '1');
    fd.append('version', '0');
    const r = await fetch('/index.php?m=statetransition&f=manage&objectType=story&productID=0', {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return await r.json();
}, strictDef);
check('Save strict workflow', saveResult.result === 'success', saveResult.message);

// === Open story detail page (SPA mode) ===
console.log('\n=== Open active story in SPA mode ===');
await page.goto('http://127.0.0.1:8080/index.php?m=story&f=view&storyID=1', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(12000);

// Find the iframe
let frame = page.frames().find(f => f !== page.mainFrame() && f.url().includes('story'));
if (!frame) {
    // Maybe SPA redirected
    frame = page.frames().find(f => f !== page.mainFrame() && f.url().length > 10);
}
if (!frame) frame = page.mainFrame();

console.log('Frame URL:', frame?.url()?.substring(0, 80));

// Look for action buttons
const buttons = await frame.evaluate(() => {
    const allEls = document.querySelectorAll('a, button, [role="button"]');
    const found = {};
    allEls.forEach(el => {
        const text = (el.textContent || '').trim().substring(0, 20);
        const visible = el.offsetHeight > 0 && el.offsetWidth > 0;
        const href = el.getAttribute('href') || '';
        // Check for action links
        if(href.includes('story-close') || href.includes('story-change') || href.includes('story-activate') ||
           href.includes('close') && href.includes('story') ||
           ['关闭','变更','激活','编辑'].includes(text)) {
            if(!found[text]) found[text] = { visible, href: href.substring(0, 60), count: 0 };
            found[text].count++;
        }
    });
    return { title: document.title, bodyLen: document.body.innerHTML.length, found };
}).catch(e => ({ error: e.message }));
console.log('Buttons found:', JSON.stringify(buttons, null, 2));

// Determine button visibility
const closeVisible = buttons.found?.['关闭']?.visible || false;
const changeVisible = buttons.found?.['变更']?.visible || false;

check('Story page loaded', !buttons.error, buttons.error || buttons.title);
check('Close button HIDDEN (strict workflow)', !closeVisible, `closeVisible=${closeVisible}`);
check('Change button VISIBLE (strict workflow)', changeVisible, `changeVisible=${changeVisible}`);

// === Reset and verify buttons reappear ===
console.log('\n=== Reset workflow ===');
await page.evaluate(async () => {
    const fd = new FormData(); fd.append('confirm', '1');
    await fetch('/index.php?m=statetransition&f=reset&objectType=story&productID=0', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
});
await page.waitForTimeout(1000);

await page.goto('http://127.0.0.1:8080/index.php?m=story&f=view&storyID=1', { waitUntil: 'domcontentloaded', timeout: 30000 });
await page.waitForTimeout(12000);
frame = page.frames().find(f => f !== page.mainFrame() && f.url().includes('story')) || page.mainFrame();

const afterReset = await frame.evaluate(() => {
    const allEls = document.querySelectorAll('a, button, [role="button"]');
    const found = {};
    allEls.forEach(el => {
        const text = (el.textContent || '').trim().substring(0, 20);
        const visible = el.offsetHeight > 0;
        const href = el.getAttribute('href') || '';
        if(href.includes('story-close') || href.includes('story-change') ||
           ['关闭','变更'].includes(text)) {
            if(!found[text]) found[text] = { visible };
        }
    });
    return found;
}).catch(e => ({}));

const closeVisibleAfterReset = afterReset['关闭']?.visible || false;
check('After reset: close button VISIBLE again', closeVisibleAfterReset, `closeVisible=${closeVisibleAfterReset}`);

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n=== Summary: ${passed} / ${results.length} passed, ${failed} failed ===`);
await browser.close();
process.exit(failed === 0 ? 0 : 1);

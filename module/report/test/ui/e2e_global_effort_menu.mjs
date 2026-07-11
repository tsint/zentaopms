/**
 * E2E test: 首页 -> BI -> 全局工时 should render immediately and survive refresh.
 *
 * Run:
 *   E2E_BASE_URL=http://127.0.0.1:8080 node module/report/test/ui/e2e_global_effort_menu.mjs
 */
import {writeFileSync} from 'fs';
import {loadPlaywright} from '../../../statetransition/test/ui/playwright-loader.mjs';

const {chromium} = await loadPlaywright();

const BASE     = process.env.E2E_BASE_URL || 'http://127.0.0.1:8080';
const ACCOUNT  = process.env.E2E_ACCOUNT || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || 'Admin1234!';

const result = {steps: [], passed: 0, failed: 0};
function step(name, ok, detail = '')
{
    result.steps.push({name, ok, detail});
    if(ok) result.passed++; else result.failed++;
    console.log(`${ok ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
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

async function clickTextInAnyFrame(page, text)
{
    for(const frame of page.frames())
    {
        const locator = frame.locator(`a:has-text("${text}"), button:has-text("${text}")`).first();
        if(await locator.count())
        {
            await locator.click({timeout: 10000});
            return frame.url();
        }
    }
    throw new Error(`Cannot find clickable text: ${text}`);
}

async function clickHrefInAnyFrame(page, hrefPart)
{
    for(const frame of page.frames())
    {
        const locator = frame.locator(`a:visible[href*="${hrefPart}"]`).first();
        if(await locator.count())
        {
            await locator.click({timeout: 10000});
            return frame.url();
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

async function visibleGlobalEffortText(page)
{
    const frame = await globalEffortFrame(page);
    if(!frame) return {ok: false, detail: 'No #globalEffortPage frame found'};

    const info = await frame.evaluate(() =>
    {
        const page = document.querySelector('#globalEffortPage');
        const rect = page.getBoundingClientRect();
        const text = page.innerText;
        const biMenuText = document.body.innerText;
        return {
            url: location.href,
            title: document.title,
            top: rect.top,
            height: rect.height,
            textLength: text.trim().length,
            hasNoDimension: !text.includes('分析维度'),
            hasManagement: text.includes('整体分析') && text.includes('总消耗工时') && text.includes('实际/预估工时'),
            hasObjectDistribution: text.includes('研发对象分布比'),
            hasStaleObjects: text.includes('停滞对象'),
            hasAccountStack: text.includes('人员工时分布'),
            hasRecords: text.includes('工时明细') && text.includes('工作内容'),
            hasBIMenu: biMenuText.includes('BI') && biMenuText.includes('全局工时')
        };
    });

    const ok = info.top <= 80
        && info.height > 200
        && info.textLength > 100
        && info.hasNoDimension
        && info.hasManagement
        && info.hasObjectDistribution
        && info.hasStaleObjects
        && info.hasAccountStack
        && info.hasRecords;
    return {ok, detail: JSON.stringify(info)};
}

async function saneGlobalEffortHeader(page)
{
    const frame = await globalEffortFrame(page);
    if(!frame) return {ok: false, detail: 'No #globalEffortPage frame found'};

    const info = await frame.evaluate(() =>
    {
        const header = document.querySelector('#header');
        const main   = document.querySelector('body > #main');
        const page   = document.querySelector('#globalEffortPage');
        const hRect  = header ? header.getBoundingClientRect() : null;
        const mRect  = main ? main.getBoundingClientRect() : null;
        const pRect  = page.getBoundingClientRect();
        const body   = document.body;
        const text   = header ? header.innerText : '';

        const ancestorIDs = [];
        let node = page.parentElement;
        while(node)
        {
            if(node.id) ancestorIDs.push(node.id);
            node = node.parentElement;
        }

        return {
            frameWidth: window.innerWidth,
            bodyWidth: body.clientWidth,
            bodyScrollWidth: body.scrollWidth,
            headerTop: hRect ? hRect.top : null,
            headerHeight: hRect ? hRect.height : null,
            headerWidth: hRect ? hRect.width : null,
            mainTop: mRect ? mRect.top : null,
            mainHeight: mRect ? mRect.height : null,
            mainWidth: mRect ? mRect.width : null,
            pageParentID: page.parentElement ? page.parentElement.id : '',
            pageAncestorIDs: ancestorIDs,
            pageTop: pRect.top,
            hasFullBIMenu: text.includes('大屏') && text.includes('透视表') && text.includes('图表') && text.includes('度量项') && text.includes('全局工时'),
            headerText: text.replace(/\s+/g, ' ').trim()
        };
    });

    const ok = info.headerHeight >= 44
        && info.headerHeight <= 56
        && info.headerWidth >= 1000
        && info.mainWidth === info.headerWidth
        && info.mainTop >= info.headerHeight - 2
        && info.mainTop <= info.headerHeight + 2
        && info.pageAncestorIDs.includes('main')
        && info.pageAncestorIDs.includes('mainContent')
        && info.pageTop >= info.mainTop
        && info.pageTop <= info.mainTop + 20
        && info.bodyScrollWidth === info.headerWidth
        && info.hasFullBIMenu;

    return {ok, detail: JSON.stringify(info)};
}

async function saneBIShell(page)
{
    const deadline = Date.now() + 8000;
    let lastInfo = null;
    while(Date.now() < deadline)
    {
        for(const frame of page.frames())
        {
            if(await frame.locator('#header').count())
            {
                const info = await frame.evaluate(() =>
                {
                    const header = document.querySelector('#header');
                    const main   = document.querySelector('body > #main');
                    const hRect  = header ? header.getBoundingClientRect() : null;
                    const mRect  = main ? main.getBoundingClientRect() : null;
                    const text   = header ? header.innerText : '';
                    return {
                        url: location.href,
                        title: document.title,
                        headerHeight: hRect ? hRect.height : null,
                        headerWidth: hRect ? hRect.width : null,
                        mainTop: mRect ? mRect.top : null,
                        mainWidth: mRect ? mRect.width : null,
                        hasFullBIMenu: text.includes('大屏') && text.includes('透视表') && text.includes('图表') && text.includes('度量项') && text.includes('全局工时'),
                        hasBuiltinScreen: document.body.innerText.includes('宏观数据盘点大屏') || document.body.innerText.includes('禅道月度应用健康度体检大屏'),
                        headerText: text.replace(/\s+/g, ' ').trim()
                    };
                });

                lastInfo = info;
                const ok = info.headerHeight >= 44
                    && info.headerHeight <= 56
                    && info.headerWidth >= 1000
                    && info.mainWidth === info.headerWidth
                    && info.mainTop >= info.headerHeight - 2
                    && info.mainTop <= info.headerHeight + 2
                    && info.hasFullBIMenu
                    && info.hasBuiltinScreen;
                if(ok) return {ok, detail: JSON.stringify(info)};
            }
        }
        await page.waitForTimeout(250);
    }
    return {ok: false, detail: lastInfo ? JSON.stringify(lastInfo) : 'No BI header frame found'};
}

async function exportedCSVHasReadableUTF8Header(page)
{
    const frame = await globalEffortFrame(page);
    if(!frame) return {ok: false, detail: 'No #globalEffortPage frame found'};

    const href = await frame.locator('#exportGlobalEffortCSV').getAttribute('href');
    const data = await frame.evaluate(async(url) =>
    {
        const response = await fetch(url, {credentials: 'include', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const bytes = Array.from(new Uint8Array(await response.arrayBuffer()));
        return {status: response.status, bytes, contentType: response.headers.get('content-type') || ''};
    }, href);
    const buffer = Buffer.from(data.bytes);
    const hasBOM = buffer.length > 3 && buffer[0] === 0xef && buffer[1] === 0xbb && buffer[2] === 0xbf;
    const text = buffer.toString('utf8').replace(/^\uFEFF/, '');
    const firstLine = text.split(/\r?\n/)[0];
    const hasHeader = firstLine.includes('来源,日期,产品,项目,执行,对象类型,对象ID,人员,耗时,剩余,工作内容');
    return {ok: data.status === 200 && hasBOM && hasHeader, detail: JSON.stringify({status: data.status, contentType: data.contentType, firstBytes: [...buffer.subarray(0, 3)], hasBOM, firstLine})};
}

const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
try
{
    const context = await browser.newContext({viewport: {width: 780, height: 493}});
    const page = await context.newPage();

    await login(page);
    step('Login or reuse existing session', !page.url().includes('m=user&f=login'), `URL: ${page.url()}`);

    await clickHrefInAnyFrame(page, 'm=screen');
    await page.waitForTimeout(1000);
    const screenHeader = await saneBIShell(page);
    step('Click BI -> 大屏 provides sane BI shell baseline', screenHeader.ok, screenHeader.detail);

    await clickTextInAnyFrame(page, '全局工时');

    const firstOpen = await visibleGlobalEffortText(page);
    step('Click BI -> 全局工时 renders non-empty page immediately', firstOpen.ok, firstOpen.detail);
    const firstHeader = await saneGlobalEffortHeader(page);
    step('Click BI -> 全局工时 keeps sane BI header layout in narrow app shell', firstHeader.ok, firstHeader.detail);

    await page.reload({waitUntil: 'domcontentloaded', timeout: 20000});
    await page.waitForTimeout(1500);
    const afterRefresh = await visibleGlobalEffortText(page);
    step('Refresh keeps 全局工时 page content and BI menu context', afterRefresh.ok && afterRefresh.detail.includes('"hasBIMenu":true'), afterRefresh.detail);
    const refreshHeader = await saneGlobalEffortHeader(page);
    step('Refresh keeps sane BI header layout in narrow app shell', refreshHeader.ok, refreshHeader.detail);

    const csvExport = await exportedCSVHasReadableUTF8Header(page);
    step('Exported CSV has UTF-8 BOM and readable Chinese header', csvExport.ok, csvExport.detail);

    await browser.close();
}
catch(e)
{
    result.failed++;
    result.steps.push({name: 'exception', ok: false, detail: e.message});
    console.error('EXCEPTION:', e.message);
}

writeFileSync('/tmp/e2e_global_effort_menu_result.json', JSON.stringify(result, null, 2));
console.log(`\nSummary: ${result.passed} passed, ${result.failed} failed`);
process.exit(result.failed === 0 ? 0 : 1);

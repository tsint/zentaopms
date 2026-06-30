#!/usr/bin/env node
import {spawn} from 'node:child_process';
import {mkdtemp, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {createRequire} from 'node:module';

const base = process.env.ZT_TEST_WEB || 'http://127.0.0.1:8080';
const account = process.env.ZT_TEST_ACCOUNT || 'admin';
const password = process.env.ZT_TEST_PASSWORD;
const sessionID = process.env.ZT_TEST_SID || '';
const storyID = process.env.ZT_TEST_STORY_ID || '1';
const bugID = process.env.ZT_TEST_BUG_ID || '1';
const taskID = process.env.ZT_TEST_TASK_ID || '900001';
const chrome = process.env.CHROME_BIN || join(process.env.HOME, '.cache/ms-playwright/chromium-1208/chrome-linux64/chrome');
const require = createRequire(import.meta.url);
const WebSocketClient = globalThis.WebSocket || require(process.env.WS_MODULE || join(process.env.HOME, '.npm-global/lib/node_modules/@marp-team/marp-cli/node_modules/ws'));
if(!sessionID && !password) throw new Error('ZT_TEST_PASSWORD is required');

const profile = await mkdtemp(join(tmpdir(), 'workflowflowchart-'));
const port = 9333;
const browser = spawn(chrome, ['--headless=new', '--no-sandbox', '--disable-gpu', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, `${base}/index.php?m=user&f=login`], {stdio: 'ignore', detached: true});

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
let sequence = 0;
const pending = new Map();
const browserErrors = [];
async function waitDebugger()
{
    for(let index = 0; index < 50; index++)
    {
        try
        {
            const targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
            const page = targets.find(target => target.type === 'page');
            if(page) return page.webSocketDebuggerUrl;
        }
        catch(error) {}
        await sleep(100);
    }
    throw new Error('Chromium debugger did not start');
}
function command(method, params = {})
{
    return new Promise((resolve, reject) =>
    {
        const id = ++sequence;
        const timer = setTimeout(() =>
        {
            pending.delete(id);
            reject(new Error(`CDP command timed out: ${method}`));
        }, 60000);
        pending.set(id, {resolve, reject, timer});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression)
{
    const response = await command('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
    if(response.exceptionDetails) throw new Error(response.exceptionDetails.text);
    return response.result.result.value;
}
async function navigate(url)
{
    await command('Page.stopLoading');
    await evaluate(`window.location.href=${JSON.stringify(url)};true`);
    await sleep(1200);
}
async function waitForMermaid()
{
    for(let index = 0; index < 20; index++)
    {
        if(await evaluate(`!!document.querySelector('.workflow-mermaid svg')`)) return true;
        await sleep(300);
    }
    return false;
}
function assert(value, message)
{
    if(!value) throw new Error(message);
}

try
{
    const debuggerURL = await waitDebugger();
    socket = new WebSocketClient(debuggerURL);
    await new Promise((resolve, reject) => {socket.onopen = resolve; socket.onerror = reject;});
    socket.onmessage = event =>
    {
        const message = JSON.parse(event.data);
        if(!message.id)
        {
            if(message.method === 'Runtime.exceptionThrown') browserErrors.push(message.params.exceptionDetails.text);
            return;
        }
        if(!pending.has(message.id)) return;
        const waiter = pending.get(message.id);
        pending.delete(message.id);
        clearTimeout(waiter.timer);
        if(message.error) waiter.reject(new Error(message.error.message)); else waiter.resolve(message);
    };
    await command('Page.enable');
    await command('Network.enable');
    await command('Runtime.enable');
    if(sessionID) await command('Network.setCookie', {name: 'zentaosid', value: sessionID, url: base});

    const editorURL = `${base}/index.php?m=workflowflowchart&f=browse&objectType=story&mode=edit&_single=1`;
    if(sessionID)
    {
        await navigate(`${editorURL}&zentaosid=${encodeURIComponent(sessionID)}`);
    }
    else
    {
        await navigate(`${base}/index.php?m=user&f=login`);
        await evaluate(`document.querySelector('#account').value=${JSON.stringify(account)};document.querySelector('#password').value=${JSON.stringify(password)};document.querySelector('[name=referer]').value=${JSON.stringify(editorURL)};document.querySelector('#submit').click();true`);
        await sleep(2200);
        assert(await evaluate(`window.config && window.config.account === ${JSON.stringify(account)}`), 'Login failed');
    }
    await evaluate(`document.cookie='tab=admin; path=/';true`);

    await navigate(editorURL);
    await waitForMermaid();
    const editorState = await evaluate(`(() => {const page=document.querySelector('.workflow-page');const diagram=document.querySelector('.workflow-mermaid');const svg=document.querySelector('.workflow-mermaid svg');const board=document.querySelector('#workflowBoard');return {url:location.href,title:document.title,text:document.body.innerText.slice(0,300),pageTop:page?Math.round(page.getBoundingClientRect().top):null,diagramTop:diagram?Math.round(diagram.getBoundingClientRect().top):null,boardTop:board?Math.round(board.getBoundingClientRect().top):null,hasDiagram:!!svg,columns:document.querySelectorAll('#workflowBoard .workflow-column').length,transitions:document.querySelectorAll('.workflow-transition').length,hasDraft:document.body.innerText.includes('草稿') || document.body.innerText.includes('Draft'),oldSvg:!!document.querySelector('#workflowBoard svg,#workflowBoard .workflow-edge')};})()`);
    assert(editorState.hasDiagram && editorState.transitions > 0 && editorState.hasDraft, `Mermaid state machine was not rendered: ${JSON.stringify(editorState)} errors=${JSON.stringify(browserErrors)}`);
    assert(editorState.pageTop <= 90 && editorState.diagramTop <= 180 && !editorState.oldSvg, `Workflow editor first viewport is invalid: ${JSON.stringify(editorState)}`);
    assert(await evaluate(`document.querySelectorAll('.workflow-tab').length === 6`), 'Object type tabs are incomplete');
    assert(await evaluate(`!!document.querySelector('#saveWorkflow')`), 'Admin editor controls are missing');
    await evaluate(`Array.from(document.querySelectorAll('.workflow-tab')).find(tab => tab.textContent.trim() === '业务需求' || tab.textContent.trim() === 'Epic').click(); true`);
    await sleep(1200);
    await waitForMermaid();
    assert(await evaluate(`location.href.includes('objectType=epic') && document.querySelector('.workflow-tab.active') && ['业务需求','Epic'].includes(document.querySelector('.workflow-tab.active').textContent.trim()) && !!document.querySelector('.workflow-mermaid svg')`), 'Clicking Epic tab did not switch the workflow editor');
    await navigate(editorURL);

    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=epic&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelectorAll('#newSource option').length === 5 && document.querySelectorAll('.workflow-transition').length > 0`), 'Epic flowchart did not render');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=requirement&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelectorAll('#newSource option').length === 5 && document.querySelectorAll('.workflow-transition').length > 0`), 'Requirement flowchart did not render');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=bug&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelectorAll('#newSource option').length === 3 && document.querySelectorAll('#newAction option').length === 3`), 'Bug flowchart did not render');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=task&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelectorAll('#newSource option').length === 6 && document.querySelectorAll('#newAction option').length === 7 && document.body.innerText.includes('开始')`), 'Task flowchart did not render');
    const taskPathBinding = await evaluate(`(() => {
        function boxDistance(point, box) {
            const dx = point.x < box.left ? box.left - point.x : (point.x > box.left + box.width ? point.x - box.left - box.width : 0);
            const dy = point.y < box.top ? box.top - point.y : (point.y > box.top + box.height ? point.y - box.top - box.height : 0);
            return Math.sqrt(dx * dx + dy * dy);
        }
        function pathPointToScreen(path, point) {
            const matrix = path.getScreenCTM();
            const svgPoint = path.ownerSVGElement.createSVGPoint();
            svgPoint.x = point.x;
            svgPoint.y = point.y;
            return matrix ? svgPoint.matrixTransform(matrix) : point;
        }
        function transitionDistance(path, sourceBox, targetBox) {
            const start = pathPointToScreen(path, path.getPointAtLength(0));
            const end = pathPointToScreen(path, path.getPointAtLength(path.getTotalLength()));
            return Math.min(
                boxDistance(start, sourceBox) + boxDistance(end, targetBox),
                boxDistance(start, targetBox) + boxDistance(end, sourceBox)
            );
        }
        const rule = document.querySelector('.workflow-transition[data-edge="done-doing-activate"]');
        if(!rule) return {rule:false};
        rule.click();
        const activePath = document.querySelector('.workflow-mermaid path.workflow-mermaid-edge-active');
        const done = document.querySelector('.workflow-mermaid [data-state-id="done"]');
        const doing = document.querySelector('.workflow-mermaid [data-state-id="doing"]');
        const closed = document.querySelector('.workflow-mermaid [data-state-id="closed"]');
        if(!activePath || !done || !doing || !closed) return {rule:true, activePath:!!activePath, done:!!done, doing:!!doing, closed:!!closed};
        const doneDistance = transitionDistance(activePath, done.getBoundingClientRect(), doing.getBoundingClientRect());
        const closedDistance = transitionDistance(activePath, closed.getBoundingClientRect(), doing.getBoundingClientRect());
        return {
            rule:true,
            activeEdge:activePath.getAttribute('data-edge-id'),
            activeSource:activePath.getAttribute('data-source'),
            activeTarget:activePath.getAttribute('data-target'),
            doneDistance,
            closedDistance
        };
    })()`);
    assert(taskPathBinding.rule && taskPathBinding.activeEdge === 'done-doing-activate' && taskPathBinding.activeSource === 'done' && taskPathBinding.activeTarget === 'doing' && taskPathBinding.doneDistance < taskPathBinding.closedDistance, `Task Mermaid path binding does not match the selected rule: ${JSON.stringify(taskPathBinding)}`);
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=testcase&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelectorAll('#newSource option').length === 4 && document.querySelectorAll('#newAction option').length === 6 && document.body.innerText.includes('待评审')`), 'Test case flowchart did not render');
    await navigate(editorURL);

    const realtimeRender = await evaluate(`(async () => {
        const before = document.querySelector('.workflow-mermaid').textContent;
        document.querySelector('#newSource').value = 'draft';
        document.querySelector('#newTarget').value = 'closed';
        document.querySelector('#newAction').value = 'close';
        document.querySelector('#addTransition').click();
        document.querySelector('#edgeLabel').value = 'Realtime Rule';
        document.querySelector('#edgeLabel').dispatchEvent(new Event('change'));
        await new Promise(resolve => setTimeout(resolve, 900));
        const afterLabel = document.querySelector('.workflow-mermaid svg') ? document.querySelector('.workflow-mermaid svg').textContent : '';
        document.querySelector('#edgeEnabled').checked = false;
        document.querySelector('#edgeEnabled').dispatchEvent(new Event('change'));
        await new Promise(resolve => setTimeout(resolve, 900));
        const afterDisable = document.querySelector('.workflow-mermaid svg') ? document.querySelector('.workflow-mermaid svg').textContent : '';
        document.querySelector('#deleteTransition').click();
        return {before, afterLabel, afterDisable};
    })()`);
    assert(realtimeRender.afterLabel.includes('Realtime Rule') && !realtimeRender.afterDisable.includes('Realtime Rule'), `Mermaid graph did not refresh after rule edits: ${JSON.stringify(realtimeRender)}`);

    const mermaidEdgeSelection = await evaluate(`(() => {
        const clickable = Array.from(document.querySelectorAll('.workflow-mermaid [data-edge-id]')).find(node => node.getAttribute('data-edge-id') === 'draft-reviewing-submitreview');
        if(!clickable) return {clickable:false, html:document.querySelector('.workflow-mermaid').innerHTML.slice(0,500)};
        clickable.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}));
        const activeRule = document.querySelector('.workflow-transition.active');
        const activeRoute = document.querySelector('.workflow-route.active');
        const activeGraphEdge = document.querySelector('.workflow-mermaid .workflow-mermaid-edge-active');
        const dimmed = document.querySelectorAll('.workflow-mermaid .workflow-mermaid-dimmed').length;
        const activeStates = document.querySelectorAll('.workflow-mermaid .workflow-mermaid-state-active').length;
        const hasSelection = document.querySelector('.workflow-mermaid').classList.contains('has-selection');
        return {
            clickable:true,
            activeRule:activeRule ? activeRule.getAttribute('data-edge') : '',
            activeRoute:activeRoute ? activeRoute.getAttribute('data-edge') : '',
            activeGraphEdge:activeGraphEdge ? activeGraphEdge.getAttribute('data-edge-id') : '',
            dimmed,
            activeStates,
            hasSelection,
            editorVisible:!document.querySelector('#ruleEditor').classList.contains('hidden'),
            label:document.querySelector('#edgeLabel').value
        };
    })()`);
    assert(mermaidEdgeSelection.clickable && mermaidEdgeSelection.activeRule === 'draft-reviewing-submitreview' && mermaidEdgeSelection.activeRoute === 'draft-reviewing-submitreview' && mermaidEdgeSelection.activeGraphEdge === 'draft-reviewing-submitreview' && mermaidEdgeSelection.dimmed > 0 && mermaidEdgeSelection.activeStates >= 2 && mermaidEdgeSelection.hasSelection && mermaidEdgeSelection.editorVisible, `Clicking a Mermaid edge did not select and emphasize its rule: ${JSON.stringify(mermaidEdgeSelection)}`);

    const mermaidToggleOff = await evaluate(`(() => {
        const clickable = Array.from(document.querySelectorAll('.workflow-mermaid [data-edge-id]')).find(node => node.getAttribute('data-edge-id') === 'draft-reviewing-submitreview');
        clickable.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}));
        return {
            activeRule:!!document.querySelector('.workflow-transition.active'),
            activeRoute:!!document.querySelector('.workflow-route.active'),
            activeGraphEdge:!!document.querySelector('.workflow-mermaid .workflow-mermaid-edge-active'),
            dimmed:document.querySelectorAll('.workflow-mermaid .workflow-mermaid-dimmed').length,
            hasSelection:document.querySelector('.workflow-mermaid').classList.contains('has-selection'),
            editorHidden:document.querySelector('#ruleEditor').classList.contains('hidden')
        };
    })()`);
    assert(!mermaidToggleOff.activeRule && !mermaidToggleOff.activeRoute && !mermaidToggleOff.activeGraphEdge && mermaidToggleOff.dimmed === 0 && !mermaidToggleOff.hasSelection && mermaidToggleOff.editorHidden, `Clicking the selected Mermaid edge again did not clear selection: ${JSON.stringify(mermaidToggleOff)}`);

    const ruleSelection = await evaluate(`(() => {
        const rule = document.querySelector('.workflow-transition[data-edge="active-changing-change"]');
        if(!rule) return {rule:false};
        rule.click();
        const activeGraphEdge = document.querySelector('.workflow-mermaid .workflow-mermaid-edge-active');
        const dimmed = document.querySelectorAll('.workflow-mermaid .workflow-mermaid-dimmed').length;
        return {
            rule:true,
            activeRule:document.querySelector('.workflow-transition.active') ? document.querySelector('.workflow-transition.active').getAttribute('data-edge') : '',
            activeGraphEdge:activeGraphEdge ? activeGraphEdge.getAttribute('data-edge-id') : '',
            dimmed,
            editorVisible:!document.querySelector('#ruleEditor').classList.contains('hidden')
        };
    })()`);
    assert(ruleSelection.rule && ruleSelection.activeRule === 'active-changing-change' && ruleSelection.activeGraphEdge === 'active-changing-change' && ruleSelection.dimmed > 0 && ruleSelection.editorVisible, `Clicking a rule did not select and emphasize its Mermaid edge: ${JSON.stringify(ruleSelection)}`);

    await evaluate(`document.querySelector('#newSource').value='draft';document.querySelector('#newTarget').value='active';document.querySelector('#newAction').value='close';document.querySelector('#addTransition').click();document.querySelector('#edgeLabel').value='E2E Rule';document.querySelector('#edgeLabel').dispatchEvent(new Event('change'));document.querySelector('#saveWorkflow').click();true`);
    await sleep(1600);
    await navigate(editorURL);
    assert(await evaluate(`document.documentElement.innerHTML.includes('E2E Rule')`), 'Saved transition was not persisted');

    await evaluate(`document.querySelector('#resetWorkflow').click();document.querySelector('#saveWorkflow').click();true`);
    await sleep(1500);
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=story&mode=view&_single=1`);
    assert(await evaluate(`!document.querySelector('#saveWorkflow') && !!document.querySelector('#readonlyRules')`), 'Read-only view exposes editor controls');

    await command('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    await sleep(500);
    assert(await evaluate(`document.documentElement.scrollWidth <= window.innerWidth + 1`), 'Mobile layout overflows horizontally');

    await command('Emulation.clearDeviceMetricsOverride');
    await navigate(`${base}/index.php?m=story&f=view&storyID=${storyID}&_single=1`);
    await sleep(1200);
    const storyEntry = await evaluate(`({flow:!!document.querySelector('.workflowflowchart-detail .workflowflowchart-board'),current:!!document.querySelector('.workflowflowchart-node.current'),routes:document.querySelectorAll('.workflowflowchart-item').length,url:location.href,text:document.body.innerText.slice(0,300)})`);
    assert(storyEntry.flow && storyEntry.routes > 0, `Story detail flowchart is missing: ${JSON.stringify(storyEntry)}`);
    await navigate(`${base}/index.php?m=bug&f=view&bugID=${bugID}&_single=1`);
    assert(await evaluate(`!!document.querySelector('.workflowflowchart-detail .workflowflowchart-board') && document.querySelectorAll('.workflowflowchart-item').length > 0`), 'Bug detail flowchart is missing');
    await navigate(`${base}/index.php?m=task&f=view&taskID=${taskID}&_single=1`);
    assert(await evaluate(`!!document.querySelector('.workflowflowchart-detail .workflowflowchart-board') && document.querySelectorAll('.workflowflowchart-item').length > 0`), 'Task detail flowchart is missing');
    console.log('workflow flowchart browser test passed');
}
finally
{
    if(socket) socket.close();
    if(browser.exitCode === null)
    {
        const exited = new Promise(resolve => browser.once('exit', resolve));
        process.kill(-browser.pid, 'SIGTERM');
        await Promise.race([exited, sleep(2000)]);
        if(browser.exitCode === null) process.kill(-browser.pid, 'SIGKILL');
    }
    await rm(profile, {recursive: true, force: true});
}

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
const featureNodesOnly = process.env.ZT_TEST_FEATURE_NODES === '1';
const edgeDeletionOnly = process.env.ZT_TEST_EDGE_DELETION === '1';
const reviewModalOnly = process.env.ZT_TEST_REVIEW_MODAL === '1';
const reviewStoryID = process.env.ZT_TEST_REVIEW_STORY_ID || '';
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

    if(edgeDeletionOnly)
    {
        await navigate(editorURL);
        await waitForMermaid();
        await evaluate(`document.querySelector('#resetWorkflow').click();true`);
        await sleep(900);
        const groupedIdentity = await evaluate(`(() => {const path=document.querySelector('.workflow-mermaid path[data-source="reviewing"][data-target="closed"]');return path?.getAttribute('data-edge-ids')||'';})()`);
        assert(groupedIdentity.split(' ').sort().join(' ') === ['reviewing-closed-review','reviewing-closed-close'].sort().join(' '), `Merged Mermaid edge does not retain all rule identities: ${groupedIdentity}`);
        await evaluate(`document.querySelector('.workflow-transition[data-edge="reviewing-closed-review"]').click();document.querySelector('#deleteTransition').click();true`);
        await sleep(900);
        const expectedEdges = ['draft-reviewing-submitreview','draft-active-review','reviewing-active-review','reviewing-draft-review','reviewing-changing-review','reviewing-closed-close','active-changing-change','changing-reviewing-submitreview','changing-active-review','draft-closed-close','active-closed-close','changing-closed-close','closed-active-activate','closed-draft-activate'];
        const binding = await evaluate(`(async () => {
            const expected = ${JSON.stringify({
                'draft-reviewing-submitreview':['draft','reviewing'], 'draft-active-review':['draft','active'],
                'reviewing-active-review':['reviewing','active'], 'reviewing-draft-review':['reviewing','draft'],
                'reviewing-changing-review':['reviewing','changing'], 'reviewing-closed-close':['reviewing','closed'],
                'active-changing-change':['active','changing'], 'changing-reviewing-submitreview':['changing','reviewing'],
                'changing-active-review':['changing','active'], 'draft-closed-close':['draft','closed'],
                'active-closed-close':['active','closed'], 'changing-closed-close':['changing','closed'],
                'closed-active-activate':['closed','active'], 'closed-draft-activate':['closed','draft']
            })};
            function point(path,at){const p=path.getPointAtLength(at),m=path.getScreenCTM(),s=path.ownerSVGElement.createSVGPoint();s.x=p.x;s.y=p.y;return s.matrixTransform(m);}
            function distance(point,box){const dx=point.x<box.left?box.left-point.x:(point.x>box.right?point.x-box.right:0),dy=point.y<box.top?box.top-point.y:(point.y>box.bottom?point.y-box.bottom:0);return Math.hypot(dx,dy);}
            const result = {missing:[],wrong:[],geometry:[],labels:[],pathCount:document.querySelectorAll('.workflow-mermaid path.transition[data-edge-id]').length,reviewRuleRemoved:!document.querySelector('.workflow-transition[data-edge="reviewing-closed-review"]')};
            for(const [edgeID,states] of Object.entries(expected))
            {
                const path = document.querySelector('.workflow-mermaid path.transition[data-edge-id="' + edgeID + '"]');
                if(!path){result.missing.push(edgeID);continue;}
                const source=document.querySelector('.workflow-mermaid [data-state-id="'+states[0]+'"]'),target=document.querySelector('.workflow-mermaid [data-state-id="'+states[1]+'"]');
                const start=point(path,0),end=point(path,path.getTotalLength()),direct=distance(start,source.getBoundingClientRect())+distance(end,target.getBoundingClientRect()),reverse=distance(start,target.getBoundingClientRect())+distance(end,source.getBoundingClientRect());
                if(direct > reverse) result.geometry.push({edgeID,direct,reverse});
                path.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                const selected = document.querySelector('.workflow-transition.active')?.dataset.edge || '';
                if(selected !== edgeID) result.wrong.push({edgeID,selected});
                path.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                const label=Array.from(document.querySelectorAll('.workflow-mermaid .edgeLabel[data-edge-id]')).find(node=>node.dataset.edgeId===edgeID);
                if(!label){result.labels.push({edgeID,reason:'missing'});continue;}
                label.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                const labelSelected=document.querySelector('.workflow-transition.active')?.dataset.edge || '';
                if(labelSelected!==edgeID)result.labels.push({edgeID,selected:labelSelected});
                label.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
            }
            return result;
        })()`);
        assert(binding.reviewRuleRemoved && binding.pathCount === expectedEdges.length && binding.missing.length === 0 && binding.wrong.length === 0 && binding.geometry.length === 0 && binding.labels.length === 0, `Mermaid edges were misbound after deleting reviewing -> closed: ${JSON.stringify(binding)} errors=${JSON.stringify(browserErrors)}`);

        await evaluate(`document.querySelector('#resetWorkflow').click();true`);
        await sleep(900);
        await evaluate(`document.querySelector('.workflow-transition[data-edge="reviewing-closed-close"]').click();document.querySelector('#deleteTransition').click();true`);
        await sleep(900);
        const mergedEdge = await evaluate(`(() => {
            const edgeID='reviewing-closed-review';
            const rule=document.querySelector('.workflow-transition[data-edge="'+edgeID+'"]');
            const path=document.querySelector('.workflow-mermaid path.transition[data-edge-id="'+edgeID+'"]');
            const label=Array.from(document.querySelectorAll('.workflow-mermaid .edgeLabel[data-edge-id]')).find(node=>node.dataset.edgeId===edgeID);
            const result={rule:!!rule,path:!!path,label:!!label,edgeIDs:path?.getAttribute('data-edge-ids')||'',closeRemoved:!document.querySelector('.workflow-transition[data-edge="reviewing-closed-close"]')};
            if(rule){rule.click();result.ruleSelected=document.querySelector('.workflow-transition.active')?.dataset.edge||'';result.graphSelected=document.querySelector('.workflow-mermaid .workflow-mermaid-edge-active')?.dataset.edgeId||'';rule.click();}
            if(path){path.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));result.pathSelected=document.querySelector('.workflow-transition.active')?.dataset.edge||'';path.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));}
            if(label){label.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));result.labelSelected=document.querySelector('.workflow-transition.active')?.dataset.edge||'';}
            return result;
        })()`);
        assert(mergedEdge.rule && mergedEdge.path && mergedEdge.label && mergedEdge.edgeIDs === 'reviewing-closed-review' && mergedEdge.closeRemoved && mergedEdge.ruleSelected === 'reviewing-closed-review' && mergedEdge.graphSelected === 'reviewing-closed-review' && mergedEdge.pathSelected === 'reviewing-closed-review' && mergedEdge.labelSelected === 'reviewing-closed-review', `Remaining member of a formerly merged edge is not selectable: ${JSON.stringify(mergedEdge)} errors=${JSON.stringify(browserErrors)}`);
        await evaluate(`document.querySelector('.workflow-transition.active')?.click();true`);
        const hitPoint = await evaluate(`(() => {const path=document.querySelector('.workflow-mermaid path.transition[data-edge-id="reviewing-closed-review"]'),matrix=path.getScreenCTM(),svgPoint=path.ownerSVGElement.createSVGPoint(),box=document.querySelector('.workflow-mermaid').getBoundingClientRect();let screen=null;for(let ratio=.1;ratio<.95;ratio+=.05){const point=path.getPointAtLength(path.getTotalLength()*ratio);svgPoint.x=point.x;svgPoint.y=point.y;const candidate=svgPoint.matrixTransform(matrix);if(candidate.x>box.left+24&&candidate.x<box.right-24&&candidate.y>box.top+24&&candidate.y<box.bottom-24){screen=candidate;break;}}if(!screen)return {x:0,y:0,hit:'',reason:'no-visible-point',box:{left:box.left,right:box.right,top:box.top,bottom:box.bottom}};const hit=document.elementFromPoint(screen.x+5,screen.y);return {x:screen.x+5,y:screen.y,hit:hit?.getAttribute('data-edge-id')||'',tag:hit?.tagName||'',className:hit?.getAttribute('class')||'',parent:hit?.parentElement?.getAttribute('class')||''};})()`);
        await command('Input.dispatchMouseEvent',{type:'mousePressed',x:hitPoint.x,y:hitPoint.y,button:'left',clickCount:1});
        await command('Input.dispatchMouseEvent',{type:'mouseReleased',x:hitPoint.x,y:hitPoint.y,button:'left',clickCount:1});
        await sleep(200);
        const realMouseSelected = await evaluate(`document.querySelector('.workflow-transition.active')?.dataset.edge||''`);
        assert(hitPoint.hit === 'reviewing-closed-review' && realMouseSelected === 'reviewing-closed-review', `Remaining merged edge has no usable mouse hit area: ${JSON.stringify({hitPoint,realMouseSelected})}`);
        console.log('workflow edge deletion binding browser test passed');
    }
    else if(featureNodesOnly)
    {
        await navigate(editorURL);
        await waitForMermaid();
        const initial = await evaluate(`(() => ({tabs:Array.from(document.querySelectorAll('.workflow-tab')).map(tab => tab.textContent.trim()),active:document.querySelector('.workflow-tab.active')?.textContent.trim(),mermaid:!!document.querySelector('.workflow-mermaid svg'),addNode:!!document.querySelector('#addNode')}))()`);
        assert(initial.mermaid && initial.addNode, `Workflow node editor is unavailable: ${JSON.stringify(initial)}`);
        assert(!initial.tabs.includes('业务需求') && !initial.tabs.includes('用户需求') && !initial.tabs.includes('Epic') && !initial.tabs.includes('User Requirement'), `Disabled requirement tabs remain visible: ${JSON.stringify(initial.tabs)}`);

        await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=epic&mode=edit&_single=1`);
        await waitForMermaid();
        assert(await evaluate(`new URL(location.href).searchParams.get('objectType') === 'epic' && ['研发需求','Story'].includes(document.querySelector('.workflow-tab.active')?.textContent.trim())`), 'Direct access to a disabled workflow type did not fall back to Story');

        await navigate(editorURL);
        await waitForMermaid();
        await evaluate(`document.querySelector('#newNodeID').value='accepted';document.querySelector('#newNodeLabel').value='已验收';document.querySelector('#addNode').click();true`);
        for(let index = 0; index < 20 && !(await evaluate(`document.querySelector('.workflow-mermaid svg')?.textContent.includes('已验收')`)); index++) await sleep(250);
        const added = await evaluate(`(() => ({column:!!document.querySelector('#workflowBoard [data-status="accepted"]'),diagram:!!document.querySelector('.workflow-mermaid svg')?.textContent.includes('已验收'),source:!!document.querySelector('#newSource option[value="accepted"]'),target:!!document.querySelector('#newTarget option[value="accepted"]'),remove:!!document.querySelector('#workflowBoard [data-status="accepted"] .workflow-delete-node')}))()`);
        assert(added.column && added.diagram && added.source && added.target && added.remove, `Custom state node was not propagated through the editor: ${JSON.stringify(added)} errors=${JSON.stringify(browserErrors)}`);
        await evaluate(`document.querySelector('#newSource').value='draft';document.querySelector('#newTarget').value='accepted';document.querySelector('#newTransitionLabel').value='不做 / 重复 / 无效';document.querySelector('#addTransition').click();true`);
        for(let index = 0; index < 20 && !(await evaluate(`document.querySelector('.workflow-mermaid svg')?.textContent.includes('不做 / 重复 / 无效')`)); index++) await sleep(250);
        const namedEdge = await evaluate(`(() => ({diagram:!!document.querySelector('.workflow-mermaid svg')?.textContent.includes('不做 / 重复 / 无效'),rule:Array.from(document.querySelectorAll('.workflow-transition')).some(node => node.textContent.includes('不做 / 重复 / 无效')),editor:document.querySelector('#edgeLabel').value,inputCleared:document.querySelector('#newTransitionLabel').value==='',selected:document.querySelector('.workflow-transition.active')?.dataset.edge||''}))()`);
        assert(namedEdge.diagram && namedEdge.rule && namedEdge.editor === '不做 / 重复 / 无效' && namedEdge.inputCleared && namedEdge.selected, `Custom transition name was not synchronized: ${JSON.stringify(namedEdge)} errors=${JSON.stringify(browserErrors)}`);
        await evaluate(`document.querySelector('#edgeLabel').value='暂不处理';document.querySelector('#edgeLabel').dispatchEvent(new Event('change'));true`);
        for(let index = 0; index < 20 && !(await evaluate(`document.querySelector('.workflow-mermaid svg')?.textContent.includes('暂不处理')`)); index++) await sleep(250);
        assert(await evaluate(`document.querySelector('.workflow-mermaid svg')?.textContent.includes('暂不处理') && Array.from(document.querySelectorAll('.workflow-transition')).some(node => node.textContent.includes('暂不处理'))`), 'Editing a transition name did not immediately refresh Mermaid and the rule list');
        await evaluate(`document.querySelector('#workflowBoard [data-status="accepted"] .workflow-delete-node').click();true`);
        await sleep(500);
        const removed = await evaluate(`!document.querySelector('#workflowBoard [data-status="accepted"]') && !document.querySelector('#newSource option[value="accepted"]') && !document.querySelector('#newTarget option[value="accepted"]') && !Array.from(document.querySelectorAll('.workflow-transition')).some(node => node.textContent.includes('暂不处理'))`);
        assert(removed, 'Custom state node and its named transition were not removed together');
        console.log('workflow feature switches, custom nodes and transition names browser test passed');
    }
    else if(reviewModalOnly)
    {
        await navigate(`${base}/index.php?m=my&f=audit`);
        let reviewLink = '';
        for(let index = 0; index < 30 && !reviewLink; index++)
        {
            reviewLink = await evaluate(`(() => {const doc=document.querySelector('#appIframe-my')?.contentDocument||document;const link=Array.from(doc.querySelectorAll('a')).find(node => /[?&]f=review(?:&|$)/.test(node.href) || /-(?:review)-/.test(node.href));return link ? link.href : '';})()`);
            if(!reviewLink) await sleep(250);
        }
        const directDetail = !!reviewStoryID;
        if(directDetail) reviewLink = `${base}/index.php?m=requirement&f=view&storyID=${encodeURIComponent(reviewStoryID)}&_single=1`;
        assert(reviewLink, 'No pending review action was found on My Audit page');
        if(directDetail) await navigate(reviewLink);
        else await evaluate(`(() => {const doc=document.querySelector('#appIframe-my')?.contentDocument||document;const link=Array.from(doc.querySelectorAll('a')).find(node => node.href===${JSON.stringify(reviewLink)});link.click();return true;})()`);
        await sleep(1800);
        for(let index = 0; index < 20 && !(await evaluate(`!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid svg')`)); index++) await sleep(250);
        const reviewState = await evaluate(`(() => {const detail=document.querySelector('.workflowflowchart-detail');return {mermaid:!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid svg'),mermaidSource:!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid pre.mermaid'),mermaidGlobal:!!window.mermaid,loader:!!document.querySelector('#workflowflowchart-mermaid-js'),legacy:!!document.querySelector('.workflowflowchart-board,.workflowflowchart-list,.workflowflowchart-item'),detailHTML:detail?detail.innerHTML.slice(0,400):'',effortButtons:document.querySelectorAll('.objecteffort-record').length,recordLinks:document.querySelectorAll('a[href*="m=objecteffort"][href*="f=record"]').length,topEffort:!!document.querySelector('#header .objecteffort-record,.main-header .objecteffort-record,header .objecteffort-record')};})()`);
        assert(reviewState.mermaid && !reviewState.legacy && reviewState.effortButtons === 0 && reviewState.recordLinks <= 1 && !reviewState.topEffort, `Audit story detail is not Mermaid-only or has duplicate effort entries: ${JSON.stringify(reviewState)} errors=${JSON.stringify(browserErrors)}`);
        if(!directDetail)
        {
            await evaluate(`(() => {const frame=document.querySelector('#appIframe-my');(frame?.contentWindow?.zui||zui).Modal.hide();return true;})()`);
            await sleep(700);
            const afterClose = await evaluate(`(() => {const doc=document.querySelector('#appIframe-my')?.contentDocument||document;return {modal:!!doc.querySelector('.modal.show'),effortButtons:document.querySelectorAll('.objecteffort-record').length+doc.querySelectorAll('.objecteffort-record').length,topEffort:!!document.querySelector('#header .objecteffort-record,.main-header .objecteffort-record,header .objecteffort-record')};})()`);
            assert(!afterClose.modal && afterClose.effortButtons === 0 && !afterClose.topEffort, `Effort entry remained after closing review modal: ${JSON.stringify(afterClose)}`);
        }
        console.log(directDetail ? 'workflow audit story detail browser test passed' : 'workflow review modal browser test passed');
    }
    else
    {
    await navigate(editorURL);
    await waitForMermaid();
    assert(await evaluate(`document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'draft'`), 'Story state machine entry must be draft only');
    const editorState = await evaluate(`(() => {const page=document.querySelector('.workflow-page');const diagram=document.querySelector('.workflow-mermaid');const svg=document.querySelector('.workflow-mermaid svg');const board=document.querySelector('#workflowBoard');return {url:location.href,title:document.title,text:document.body.innerText.slice(0,300),pageTop:page?Math.round(page.getBoundingClientRect().top):null,diagramTop:diagram?Math.round(diagram.getBoundingClientRect().top):null,boardTop:board?Math.round(board.getBoundingClientRect().top):null,hasDiagram:!!svg,columns:document.querySelectorAll('#workflowBoard .workflow-column').length,transitions:document.querySelectorAll('.workflow-transition').length,hasDraft:document.body.innerText.includes('草稿') || document.body.innerText.includes('Draft'),oldSvg:!!document.querySelector('#workflowBoard svg,#workflowBoard .workflow-edge')};})()`);
    assert(editorState.hasDiagram && editorState.transitions > 0 && editorState.hasDraft, `Mermaid state machine was not rendered: ${JSON.stringify(editorState)} errors=${JSON.stringify(browserErrors)}`);
    assert(editorState.pageTop <= 90 && editorState.diagramTop <= 180 && !editorState.oldSvg, `Workflow editor first viewport is invalid: ${JSON.stringify(editorState)}`);
    assert(await evaluate(`!!document.querySelector('.workflow-node-section #workflowBoard') && !!document.querySelector('.workflow-rule-section #workflowList') && document.querySelector('.workflow-node-section .workflow-section-head').textContent.trim() !== document.querySelector('.workflow-rule-section .workflow-section-head').textContent.trim()`), 'State node section and transition rule section are not visually separated');
    assert(await evaluate(`document.querySelectorAll('.workflow-tab').length === 6`), 'Object type tabs are incomplete');
    assert(await evaluate(`!!document.querySelector('#saveWorkflow')`), 'Admin editor controls are missing');
    await evaluate(`Array.from(document.querySelectorAll('.workflow-tab')).find(tab => tab.textContent.trim() === '业务需求' || tab.textContent.trim() === 'Epic').click(); true`);
    await sleep(1200);
    await waitForMermaid();
    assert(await evaluate(`location.href.includes('objectType=epic') && document.querySelector('.workflow-tab.active') && ['业务需求','Epic'].includes(document.querySelector('.workflow-tab.active').textContent.trim()) && !!document.querySelector('.workflow-mermaid svg')`), 'Clicking Epic tab did not switch the workflow editor');
    await navigate(editorURL);

    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=epic&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'draft' && document.querySelectorAll('#newSource option').length === 5 && document.querySelectorAll('.workflow-transition').length > 0`), 'Epic flowchart did not render with draft as its only entry');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=requirement&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'draft' && document.querySelectorAll('#newSource option').length === 5 && document.querySelectorAll('.workflow-transition').length > 0`), 'Requirement flowchart did not render with draft as its only entry');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=bug&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'active' && document.querySelectorAll('#newSource option').length === 3 && document.querySelectorAll('#newAction option').length === 3`), 'Bug flowchart did not render with active as its only entry');
    await navigate(`${base}/index.php?m=workflowflowchart&f=browse&objectType=task&mode=edit&_single=1`);
    await waitForMermaid();
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'wait' && document.querySelectorAll('#newSource option').length === 6 && document.querySelectorAll('#newAction option').length === 7 && document.body.innerText.includes('开始')`), 'Task flowchart did not render with wait as its only entry');
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
    assert(await evaluate(`!!document.querySelector('.workflow-mermaid svg') && document.querySelector('#workflowMermaid').dataset.entryStates === 'normal' && document.querySelectorAll('#newSource option').length === 4 && document.querySelectorAll('#newAction option').length === 6 && document.body.innerText.includes('待评审')`), 'Test case flowchart did not render with normal as its only entry');
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

    const ruleToggleOff = await evaluate(`(() => {
        const rule = document.querySelector('.workflow-transition[data-edge="active-changing-change"]');
        rule.click();
        return {
            activeRule:!!document.querySelector('.workflow-transition.active'),
            activeRoute:!!document.querySelector('.workflow-route.active'),
            activeGraphEdge:!!document.querySelector('.workflow-mermaid .workflow-mermaid-edge-active'),
            editorHidden:document.querySelector('#ruleEditor').classList.contains('hidden')
        };
    })()`);
    assert(!ruleToggleOff.activeRule && !ruleToggleOff.activeRoute && !ruleToggleOff.activeGraphEdge && ruleToggleOff.editorHidden, `Clicking the selected rule again did not clear selection: ${JSON.stringify(ruleToggleOff)}`);

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
    const storyEntry = await evaluate(`({flow:!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid svg'),legacy:!!document.querySelector('.workflowflowchart-board,.workflowflowchart-list,.workflowflowchart-item'),url:location.href,text:document.body.innerText.slice(0,300)})`);
    assert(storyEntry.flow && !storyEntry.legacy, `Story detail Mermaid flowchart is missing or legacy lists remain: ${JSON.stringify(storyEntry)}`);
    await navigate(`${base}/index.php?m=bug&f=view&bugID=${bugID}&_single=1`);
    assert(await evaluate(`!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid svg') && !document.querySelector('.workflowflowchart-board,.workflowflowchart-list,.workflowflowchart-item')`), 'Bug detail Mermaid flowchart is missing or legacy lists remain');
    await navigate(`${base}/index.php?m=task&f=view&taskID=${taskID}&_single=1`);
    assert(await evaluate(`!!document.querySelector('.workflowflowchart-detail .workflowflowchart-mermaid svg') && !document.querySelector('.workflowflowchart-board,.workflowflowchart-list,.workflowflowchart-item')`), 'Task detail Mermaid flowchart is missing or legacy lists remain');
    console.log('workflow flowchart browser test passed');
    }
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

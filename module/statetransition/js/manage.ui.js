/* Statetransition visual workflow editor (manage page).
 *
 * Layout:
 *   - Mermaid diagram with clickable edges (select/deselect)
 *   - Node matrix: one column per source status, lists outgoing transitions
 *   - Transitions list: horizontal cards
 *   - Right panel: edit selected transition's properties + add new
 *
 * Data flow:
 *   - All editor state lives in `state.definition` (mutated in place)
 *   - On save, POST full definition back to control::manage
 *
 * SPA compatibility:
 *   - ZenTao SPA's updatePageJS injects JS before the HTML elements are in DOM.
 *   - The IIFE must NOT bail early if #workflowEditor is missing; instead, set up
 *     a MutationObserver that fires init() when #workflowEditor appears.
 */
(function(){
    'use strict';
    /* === State (loaded from #workflowEditor data-* attributes when init runs) === */
    let state = null;
    function loadState() {
        const root = document.getElementById('workflowEditor');
        if(!root) return null;
        return {
            objectType: root.dataset.objectType,
            productID:  parseInt(root.dataset.productId, 10) || 0,
            version:    parseInt(root.dataset.version, 10) || 0,
            isDefault:  root.dataset.isDefault === '1',
            enabled:    root.dataset.enabled === '1',
            definition: JSON.parse(root.dataset.definition || '{}'),
            defaultDef: JSON.parse(root.dataset.defaultDefinition || '{}'),
            statusList: JSON.parse(root.dataset.statusList || '[]'),
            roleList:   JSON.parse(root.dataset.roleList || '{}'),
            users:      JSON.parse(root.dataset.users || '{}'),
            colorPresets: JSON.parse(root.dataset.colorPresets || '[]'),
            actions:    JSON.parse(root.dataset.actions || '{}'),
            systemStatuses: JSON.parse(root.dataset.systemStatuses || '[]'),
            saveUrl:    root.dataset.saveUrl,
            browseUrl:  root.dataset.browseUrl,
            lang:       JSON.parse(root.dataset.lang || '{}'),
            selectedEdgeKey: null,
        };
    }
    /* === Helpers === */
    const $  = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));
    function statusLabel(key) {
        const s = state.statusList.find(x => x.key === key);
        if(s) return s.text;
        const def = (state.definition.statuses || []).find(x => x.key === key);
        return def ? (def.label && (def.label.zh_cn || def.label.en) || key) : key;
    }
    function statusColor(key) {
        const def = (state.definition.statuses || []).find(x => x.key === key);
        return def ? def.color : '#999';
    }
    function isSystemStatus(key) { return state.systemStatuses.indexOf(key) !== -1; }
    function actionLabel(action) { return state.actions[action] || action; }
    function edgeLabel(tr) {
        if(tr.label && typeof tr.label === 'object' && (tr.label.zh_cn || tr.label.en)) return tr.label.zh_cn || tr.label.en;
        const al = actionLabel(tr.action);
        return tr.branch ? al + '/' + tr.branch : al;
    }
    function entries() { return state.definition.entries || []; }
    function setEntries(arr) { state.definition.entries = arr; }
    function showToast(msg, kind) {
        kind = kind || 'loading';
        let t = document.getElementById('workflowToast');
        if(!t) { t = document.createElement('div'); t.id = 'workflowToast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = 'show ' + kind;
        if(kind !== 'loading') setTimeout(() => { t.className = ''; }, 2400);
    }
    function hideToast() {
        const t = document.getElementById('workflowToast');
        if(t) t.className = '';
    }
    /* === Mermaid rendering === */
    function generateMermaidSource(def) {
        const lines = ['stateDiagram-v2'];
        for(const entry of (def.entries || [])) lines.push('    [*] --> ' + entry);
        const usedStatuses = new Set();
        for(const tr of (def.transitions || [])) {
            if(!tr.enabled) continue;
            const label = tr.action + (tr.branch ? '/' + tr.branch : '');
            lines.push('    ' + tr.fromStatus + ' --> ' + tr.toStatus + ' : ' + label);
            usedStatuses.add(tr.fromStatus);
            usedStatuses.add(tr.toStatus);
        }
        /* Standalone state declarations for unused statuses (forces mermaid to render them).
           Mermaid stateDiagram-v2 only renders states that appear in a transition or are
           explicitly declared. Without this, an isolated custom status would be invisible. */
        for(const s of (def.statuses || [])) {
            if(usedStatuses.has(s.key)) continue;
            /* Check if this status is an entry (already shown via [*] → X). */
            if((def.entries || []).indexOf(s.key) !== -1) continue;
            /* Mermaid stateDiagram-v2: "Label" as stateId */
            const lbl = (s.label && (s.label.zh_cn || s.label.en)) || s.key;
            lines.push('    state "' + String(lbl).replace(/"/g, "'") + '" as ' + s.key);
        }
        return lines.join('\n');
    }
    function renderMermaid() {
        /* Wait for mermaid library with retries (ZenTao loads it via <script src> in #main,
           which may complete after init() runs). */
        if(typeof mermaid === 'undefined') {
            let attempts = 0;
            const wait = setInterval(() => {
                attempts++;
                if(typeof mermaid !== 'undefined') {
                    clearInterval(wait);
                    renderMermaid();
                } else if(attempts > 40) { /* ~8 seconds max */
                    clearInterval(wait);
                    console.warn('[statetransition] Mermaid library failed to load after 8s');
                    const el = document.getElementById('workflowMermaid');
                    if(el) el.innerHTML = '<div class="workflow-hint">流程图库加载失败，请刷新页面。</div>';
                }
            }, 200);
            return;
        }
        try { mermaid.initialize({ startOnLoad: false, securityLevel: 'loose', flowchart: { useMaxWidth: true, htmlLabels: true } }); } catch(e) { /* ignore */ }
        const el = document.getElementById('workflowMermaid');
        if(!el) return;
        el.innerHTML = generateMermaidSource(state.definition);
        el.removeAttribute('data-processed');
        /* Mermaid 10+: use mermaid.run */
        const promise = (mermaid.run)
            ? mermaid.run({ nodes: [el] })
            : new Promise((res) => { mermaid.init(undefined, el); res(); });
        promise.then(() => wireEdges()).catch((e) => {
            console.warn('[statetransition] Mermaid render error:', e);
            el.innerHTML = '<div class="workflow-hint">流程图渲染失败，请检查定义。</div>';
        });
    }
    /* Build edge-key → SVG-element mapping after Mermaid renders.
     *
     * Mermaid stateDiagram-v2 emits paths and labels in CORRESPONDING ORDER:
     *   g.edgePaths > path  ← all transition edges INCLUDING entry edges ([*] → X)
     *   g.edgeLabels > g.edgeLabel  ← same order; entry edges have EMPTY label text
     *
     * Strategy:
     *   1. Pair paths[i] with labels[i] by index (they correspond).
     *   2. Skip pairs where label text is empty (entry edges — no transition).
     *   3. For remaining pairs, match the label text to a transition's
     *      `action` or `action/branch` to find the transition.key.
     *   4. Set data-edge-key on BOTH the path and the label.
     */
    function wireEdges() {
        const mermaidEl = document.getElementById('workflowMermaid');
        const enabledTransitions = (state.definition.transitions || []).filter(t => t.enabled);
        if(enabledTransitions.length === 0) { applySelectionStyles(); return; }
        /* Find edge paths (stateDiagram: g.edgePaths > path ; flowchart: .edgePath / .flowchart-link). */
        let edgePathEls = $$('g.edgePaths > path', mermaidEl);
        if(edgePathEls.length === 0) edgePathEls = $$('.edgePath > path, .edgePath', mermaidEl);
        /* Find edge labels — must be DIRECT children of g.edgeLabels (one per edge, in same
           order as paths). Avoid `.edgeLabel` (matches nested children too, doubling count). */
        const edgeLabelEls = $$('g.edgeLabels > g.edgeLabel', mermaidEl);
        /* Fallback for flowchart or other Mermaid formats. */
        if(edgeLabelEls.length === 0) edgeLabelEls = $$('.edgePath .edgeLabel', mermaidEl);
        /* Track which transition keys are already paired (handles duplicate label texts). */
        const usedKeys = new Set();
        function findTransitionByText(text) {
            const tr = enabledTransitions.find(t => {
                if(usedKeys.has(t.key)) return false;
                const tText = t.action + (t.branch ? '/' + t.branch : '');
                return tText === text;
            });
            if(tr) usedKeys.add(tr.key);
            return tr;
        }
        /* Walk paths and labels together by INDEX (they're in corresponding order).
           Skip pairs where label text is empty (entry edges [*] → X have no label). */
        const minLen = Math.min(edgePathEls.length, edgeLabelEls.length);
        for(let i = 0; i < minLen; i++) {
            const path = edgePathEls[i];
            const labelEl = edgeLabelEls[i];
            const text = (labelEl.textContent || '').trim();
            if(!text) continue;  /* Entry edge ([*] → X) — skip. */
            const tr = findTransitionByText(text);
            if(!tr) continue;
            path.setAttribute('data-edge-key', tr.key);
            path.style.cursor = 'pointer';
            labelEl.setAttribute('data-edge-key', tr.key);
            labelEl.style.cursor = 'pointer';
            labelEl.addEventListener('click', () => selectEdge(tr.key));
            path.addEventListener('click', () => selectEdge(tr.key));
        }
        applySelectionStyles();
    }
    function applySelectionStyles() {
        const mermaidEl = document.getElementById('workflowMermaid');
        mermaidEl.classList.toggle('has-selection', state.selectedEdgeKey !== null);
        $$('[data-edge-key]', mermaidEl).forEach(el => {
            el.classList.toggle('is-selected', el.getAttribute('data-edge-key') === state.selectedEdgeKey);
        });
        /* Highlight source/target nodes when an edge is selected. */
        $$('.node', mermaidEl).forEach(n => n.classList.remove('is-selected'));
        if(state.selectedEdgeKey) {
            const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
            if(tr) {
                /* Mermaid node IDs may be the status key as-is (if alphanumeric). */
                [tr.fromStatus, tr.toStatus].forEach(status => {
                    const safeId = String(status).replace(/[^A-Za-z0-9_]/g, '_');
                    const node = mermaidEl.querySelector('#' + CSS.escape(safeId) + ', [id="' + safeId + '"]');
                    if(node) node.classList.add('is-selected');
                });
            }
        }
    }
    /* === Render: matrix (node columns) === */
    function renderMatrix() {
        const board = document.getElementById('workflowBoard');
        if(!board) return;
        const outgoing = {};
        (state.definition.transitions || []).forEach(tr => {
            if(!tr.enabled) return;
            (outgoing[tr.fromStatus] = outgoing[tr.fromStatus] || []).push(tr);
        });
        const html = (state.definition.statuses || []).map(status => {
            const key = status.key;
            const label = statusLabel(key);
            const isEntry = entries().indexOf(key) !== -1;
            const sys = isSystemStatus(key) || !empty(status.isSystem);
            const outs = outgoing[key] || [];
            const routes = outs.length === 0
                ? '<div class="workflow-hint">' + (state.lang.flowEmpty || '暂无') + '</div>'
                : outs.map(tr => {
                    const lbl = edgeLabel(tr);
                    const sel = tr.key === state.selectedEdgeKey ? ' is-selected' : '';
                    return '<button type="button" class="workflow-route' + sel + '" data-edge="' + esc(tr.key) + '">'
                         +   '<span class="workflow-action-label">' + esc(lbl) + '</span>'
                         +   '<span class="workflow-arrow">→</span>'
                         +   '<span class="workflow-target">' + esc(statusLabel(tr.toStatus)) + '</span>'
                         + '</button>';
                }).join('');
            const toggle = isEntry ? (state.lang.unsetEntry || '取消初始') : (state.lang.setAsEntry || '设为初始');
            const delBtn = sys ? '' : '<button type="button" class="workflow-node-delete" data-delete-node="' + esc(key) + '" title="' + esc(state.lang.deleteNode || '删除') + '">×</button>';
            return '<section class="workflow-column" data-status="' + esc(key) + '">'
                 +   '<div class="workflow-node' + (isEntry ? ' is-entry' : '') + '" style="border-left-color:' + esc(status.color || '#999') + '">'
                 +     '<span class="workflow-node-name">' + esc(label) + '</span>'
                 +     '<button type="button" class="workflow-entry-toggle' + (isEntry ? ' is-entry' : '') + '" data-toggle-entry="' + esc(key) + '">' + esc(toggle) + '</button>'
                 +     delBtn
                 +   '</div>'
                 +   routes
                 + '</section>';
        }).join('');
        board.innerHTML = html;
    }
    /* === Render: transitions list === */
    function renderList() {
        const list = document.getElementById('workflowList');
        if(!list) return;
        const trs = state.definition.transitions || [];
        if(trs.length === 0) {
            list.innerHTML = '<div class="workflow-rule-empty">' + esc(state.lang.flowEmpty || '暂无启用的流转规则。') + '</div>';
            return;
        }
        list.innerHTML = trs.map(tr => {
            const lbl = edgeLabel(tr);
            const cls = 'workflow-transition'
                + (tr.enabled ? '' : ' disabled')
                + (empty(tr.isCustom) ? '' : ' is-custom')
                + (tr.key === state.selectedEdgeKey ? ' is-selected' : '');
            return '<button type="button" class="' + cls + '" data-edge="' + esc(tr.key) + '">'
                 +   '<span class="workflow-state">' + esc(statusLabel(tr.fromStatus)) + '</span>'
                 +   '<span class="workflow-arrow">→</span>'
                 +   '<span class="workflow-action-label">' + esc(lbl) + '</span>'
                 +   '<span class="workflow-arrow">→</span>'
                 +   '<span class="workflow-state">' + esc(statusLabel(tr.toStatus)) + '</span>'
                 + '</button>';
        }).join('');
    }
    /* === Render: right panel edit form === */
    function renderPanel() {
        const editor = document.getElementById('ruleEditor');
        const empty  = document.getElementById('ruleEmpty');
        if(!state.selectedEdgeKey) {
            editor.classList.add('hidden');
            empty.classList.remove('hidden');
            editor.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
        if(!tr) {
            state.selectedEdgeKey = null;
            renderPanel();
            return;
        }
        empty.classList.add('hidden');
        editor.classList.remove('hidden');
        const labelStr = (tr.label && (tr.label.zh_cn || tr.label.en)) || '';
        setVal('edgeLabel', labelStr);
        setCheckboxGroup('edgeRoles', tr.roles || []);
        setCheckboxGroup('edgeAccounts', tr.accounts || []);
        setChecked('edgeRequireComment', !empty2(tr.requireComment));
        setChecked('edgeEnabled', empty2(tr.enabled) ? false : tr.enabled !== false);
        setChecked('edgeIsCustom', !empty2(tr.isCustom));
        const btnLabelStr = (tr.buttonLabel && (tr.buttonLabel.zh_cn || tr.buttonLabel.en)) || '';
        setVal('edgeButtonLabel', btnLabelStr);
        setVal('edgeButtonIcon', tr.buttonIcon || '');
        setVal('edgeDescription', tr.description || '');
        document.getElementById('customButtonFields').classList.toggle('hidden', empty2(tr.isCustom));
    }
    /* === Selection === */
    function selectEdge(key) {
        if(state.selectedEdgeKey === key) {
            state.selectedEdgeKey = null;
        } else {
            state.selectedEdgeKey = key;
        }
        applySelectionStyles();
        renderMatrix();
        renderList();
        renderPanel();
    }
    /* === Mutations === */
    function addNode() {
        const key = $('#newNodeKey').value.trim();
        const label = $('#newNodeLabel').value.trim();
        const category = $('#newNodeCategory').value;
        const color = $('input[name=newNodeColor]:checked') ? $('input[name=newNodeColor]:checked').value : '#999';
        if(!/^[a-z][a-z0-9_]{1,29}$/.test(key)) { showToast('状态 key 必须以小写字母开头，仅含小写字母/数字/下划线（2-30 字符）', 'error'); return; }
        if(!label) { showToast('请填写显示名称', 'error'); return; }
        if((state.definition.statuses || []).some(s => s.key === key)) { showToast('状态 key 已存在: ' + key, 'error'); return; }
        if(isSystemStatus(key)) { showToast('不能使用系统保留 key: ' + key, 'error'); return; }
        state.definition.statuses = state.definition.statuses || [];
        state.definition.statuses.push({
            key: key,
            label: {zh_cn: label, en: label},
            category: category,
            color: color,
            isSystem: false,
            isEntry: false,
            fieldRules: {}
        });
        state.statusList.push({key: key, text: label, color: color});
        $('#newNodeKey').value = '';
        $('#newNodeLabel').value = '';
        syncDataset();
        fullRender();
        renderDropdowns();
        showToast('已添加状态 ' + label, 'success');
    }
    function deleteNode(key) {
        if(!confirm(state.lang.confirmDeleteNode || '确定删除此状态吗？关联的所有流转也会被删除。')) return;
        state.definition.statuses = (state.definition.statuses || []).filter(s => s.key !== key);
        state.definition.transitions = (state.definition.transitions || []).filter(t => t.fromStatus !== key && t.toStatus !== key);
        state.definition.entries = (state.definition.entries || []).filter(e => e !== key);
        state.statusList = state.statusList.filter(s => s.key !== key);
        if(state.selectedEdgeKey) {
            const stillExists = (state.definition.transitions || []).some(t => t.key === state.selectedEdgeKey);
            if(!stillExists) state.selectedEdgeKey = null;
        }
        syncDataset();
        fullRender();
        renderDropdowns();
        showToast('已删除状态', 'success');
    }
    /* Refresh #newSource and #newTarget dropdowns from state.statusList.
       Called after add/delete node so new statuses become selectable. */
    function renderDropdowns() {
        const sourceSel = $('#newSource');
        const targetSel = $('#newTarget');
        if(!sourceSel || !targetSel) return;
        const prevSource = sourceSel.value;
        const prevTarget = targetSel.value;
        const options = state.statusList.map(s => '<option value="' + esc(s.key) + '">' + esc(s.text) + '</option>').join('');
        sourceSel.innerHTML = options;
        targetSel.innerHTML = options;
        /* Preserve previous selection if still valid. */
        if(state.statusList.some(s => s.key === prevSource)) sourceSel.value = prevSource;
        if(state.statusList.some(s => s.key === prevTarget)) targetSel.value = prevTarget;
    }
    function toggleEntry(key) {
        const arr = entries().slice();
        const idx = arr.indexOf(key);
        const willBeEntry = (idx === -1);
        if(willBeEntry) arr.push(key); else arr.splice(idx, 1);
        setEntries(arr);
        /* Keep status.isEntry in sync with entries[] — server's normalize looks at both. */
        const status = (state.definition.statuses || []).find(s => s.key === key);
        if(status) status.isEntry = willBeEntry;
        /* Sync dataset.definition so external readers (tests, devtools) see latest state. */
        syncDataset();
        /* Entry state affects mermaid ([*] → X arrows), so re-render matrix AND mermaid. */
        renderMatrix();
        renderMermaid();
    }
    /* Write state.definition back to #workflowEditor dataset so external observers see updates. */
    function syncDataset() {
        const root = document.getElementById('workflowEditor');
        if(root && state && state.definition) root.dataset.definition = JSON.stringify(state.definition);
    }
    function addTransition() {
        const from = $('#newSource').value;
        const to   = $('#newTarget').value;
        const action = $('#newAction').value;
        const label = $('#newTransitionLabel').value.trim();
        /* Read roles + requireComment from the add form directly. */
        const roles = Array.from(document.querySelectorAll('input[name="newRoles"]:checked')).map(cb => cb.value);
        const requireComment = $('#newRequireComment')?.checked || false;

        if(from === to) { showToast('起始状态和目标状态不能相同', 'error'); return; }
        const trKey = from + '-to-' + to + '-via-' + action;
        if((state.definition.transitions || []).some(t => t.key === trKey)) { showToast('已存在相同的转移: ' + trKey, 'error'); return; }
        const dupTriple = (state.definition.transitions || []).some(t => t.enabled && t.fromStatus === from && t.action === action && (t.branch || null) === null);
        if(dupTriple) { showToast('同一状态和动作只能配置一条启用的流转', 'error'); return; }

        const labelObj = label ? {zh_cn: label, en: label} : {zh_cn: actionLabel(action), en: actionLabel(action)};
        /* Save user-entered name as BOTH label (diagram) and buttonLabel (detail page button text).
           When buttonLabel is empty, the detail page falls back to the native action label,
           which is confusing when the user has explicitly customized the transition. */
        const buttonLabelObj = label ? {zh_cn: label, en: label} : null;
        state.definition.transitions = state.definition.transitions || [];
        state.definition.transitions.push({
            key: trKey,
            fromStatus: from,
            toStatus: to,
            action: action,
            branch: null,
            label: labelObj,
            roles: roles,
            accounts: [],
            requireComment: requireComment,
            enabled: true,
            isCustom: false,
            buttonLabel: buttonLabelObj,
            buttonIcon: null,
            buttonOrder: 0,
            buttonGroup: 'primary',
            sideEffects: [],
            condition: null
        });
        /* Reset add form. */
        $('#newTransitionLabel').value = '';
        document.querySelectorAll('input[name="newRoles"]').forEach(cb => cb.checked = false);
        if($('#newRequireComment')) $('#newRequireComment').checked = false;
        syncDataset();
        fullRender();
        selectEdge(trKey);
        showToast('已添加转移', 'success');
    }
    function deleteSelectedTransition() {
        if(!state.selectedEdgeKey) return;
        if(!confirm(state.lang.confirmDeleteEdge || '确定删除这条流转吗？')) return;
        state.definition.transitions = (state.definition.transitions || []).filter(t => t.key !== state.selectedEdgeKey);
        state.selectedEdgeKey = null;
        fullRender();
        showToast('已删除', 'success');
    }
    function updateSelectedTransition(field, value) {
        if(!state.selectedEdgeKey) return;
        const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
        if(!tr) return;
        tr[field] = value;
        syncDataset();
        renderMatrix();
        renderList();
    }
    function updateSelectedTransitionLabel(value) {
        if(!state.selectedEdgeKey) return;
        const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
        if(!tr) return;
        tr.label = {zh_cn: value, en: value};
        renderMatrix();
        renderList();
    }
    /* === Save === */
    async function save() {
        showToast('保存中...', 'loading');
        const body = new FormData();
        body.append('definition', JSON.stringify(state.definition));
        body.append('enabled', state.enabled ? '1' : '0');
        body.append('version', state.version);
        try {
            const resp = await fetch(state.saveUrl, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();
            if(result.result === 'success') {
                state.version = result.version;
                state.isDefault = false;
                showToast(state.lang.saveSuccess || '保存成功', 'success');
                /* Stay on manage page so user can continue editing other transitions.
                   Refresh dataset/version state from server response. */
                syncDataset();
                renderPanel();
            } else {
                showToast(result.message || '保存失败', 'error');
            }
        } catch(e) {
            showToast('网络错误: ' + e.message, 'error');
        }
    }
    function reset() {
        if(!confirm(state.lang.confirmReset || '确定恢复默认？')) return;
        window.location.href = state.browseUrl;
    }
    /* === Full render (after any mutation that changes structure) === */
    function fullRender() {
        syncDataset();
        renderMatrix();
        renderList();
        renderPanel();
        renderMermaid();
    }
    /* === Wiring === */
    function init() {
        /* Load state from #workflowEditor data-* attributes. */
        state = loadState();
        if(!state) return;
        /* Header actions */
        const saveBtn = $('#saveWorkflow');
        const resetBtn = $('#resetWorkflow');
        const enabledChk = $('#workflowEnabled');
        if(saveBtn) saveBtn.addEventListener('click', save);
        if(resetBtn) resetBtn.addEventListener('click', reset);
        if(enabledChk) enabledChk.addEventListener('change', e => { state.enabled = e.target.checked; });
        /* Add node / transition */
        const addNodeBtn = $('#addNode');
        const addTrBtn = $('#addTransition');
        if(addNodeBtn) addNodeBtn.addEventListener('click', addNode);
        if(addTrBtn) addTrBtn.addEventListener('click', addTransition);
        /* Color picker swatches */
        $$('#newNodeColorRow .workflow-color-swatch').forEach(sw => {
            sw.addEventListener('click', () => {
                $$('#newNodeColorRow .workflow-color-swatch').forEach(s => s.classList.remove('selected'));
                sw.classList.add('selected');
                const input = sw.querySelector('input');
                if(input) input.checked = true;
            });
        });
        /* Edit panel */
        const deleteTrBtn = $('#deleteTransition');
        if(deleteTrBtn) deleteTrBtn.addEventListener('click', deleteSelectedTransition);
        const edgeLabelEl = $('#edgeLabel');
        if(edgeLabelEl) edgeLabelEl.addEventListener('input', e => updateSelectedTransitionLabel(e.target.value));
        /* Roles/accounts are now checkbox groups — listen on the container div for change events. */
        const edgeRolesContainer = $('#edgeRolesList');
        if(edgeRolesContainer) edgeRolesContainer.addEventListener('change', () => {
            const vals = getCheckboxGroup('edgeRoles');
            updateSelectedTransition('roles', vals);
        });
        const edgeAccContainer = $('#edgeAccountsList');
        if(edgeAccContainer) edgeAccContainer.addEventListener('change', () => {
            const vals = getCheckboxGroup('edgeAccounts');
            updateSelectedTransition('accounts', vals);
        });
        const edgeReqEl = $('#edgeRequireComment');
        if(edgeReqEl) edgeReqEl.addEventListener('change', e => updateSelectedTransition('requireComment', e.target.checked));
        const edgeEnEl = $('#edgeEnabled');
        if(edgeEnEl) edgeEnEl.addEventListener('change', e => updateSelectedTransition('enabled', e.target.checked));
        const edgeCustomEl = $('#edgeIsCustom');
        if(edgeCustomEl) edgeCustomEl.addEventListener('change', e => {
            updateSelectedTransition('isCustom', e.target.checked);
            $('#customButtonFields').classList.toggle('hidden', !e.target.checked);
            if(e.target.checked && !$('#edgeButtonLabel').value) {
                const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
                if(tr && (!tr.buttonLabel || !tr.buttonLabel.zh_cn)) {
                    tr.buttonLabel = tr.label;
                    $('#edgeButtonLabel').value = tr.label.zh_cn || '';
                }
            }
        });
        const btnLabelEl = $('#edgeButtonLabel');
        if(btnLabelEl) btnLabelEl.addEventListener('input', e => {
            if(!state.selectedEdgeKey) return;
            const tr = (state.definition.transitions || []).find(t => t.key === state.selectedEdgeKey);
            if(tr) tr.buttonLabel = {zh_cn: e.target.value, en: e.target.value};
        });
        const btnIconEl = $('#edgeButtonIcon');
        if(btnIconEl) btnIconEl.addEventListener('input', e => updateSelectedTransition('buttonIcon', e.target.value));
        const descEl = $('#edgeDescription');
        if(descEl) descEl.addEventListener('input', e => updateSelectedTransition('description', e.target.value));
        /* Delegated clicks for matrix & list (since they re-render).
           Note: use button[data-edge] not [data-edge] — Mermaid SVG paths also have
           data-edge attribute, which would shadow our matrix/list clicks. */
        document.addEventListener('click', e => {
            const route = e.target.closest('button[data-edge], a[data-edge]');
            if(route) {
                e.preventDefault();
                selectEdge(route.getAttribute('data-edge'));
                return;
            }
            const toggleBtn = e.target.closest('[data-toggle-entry]');
            if(toggleBtn) { e.preventDefault(); toggleEntry(toggleBtn.getAttribute('data-toggle-entry')); return; }
            const delNode = e.target.closest('[data-delete-node]');
            if(delNode) { e.preventDefault(); deleteNode(delNode.getAttribute('data-delete-node')); return; }
        });
        /* Initial render. */
        renderMatrix();
        renderList();
        renderPanel();
        if(typeof mermaid !== 'undefined') {
            renderMermaid();
        } else {
            /* Wait a bit in case script is still loading. */
            setTimeout(() => { if(typeof mermaid !== 'undefined') renderMermaid(); }, 500);
        }
    }
    /* === Tiny DOM helpers === */
    function setVal(id, v) { const el = document.getElementById(id); if(el) el.value = v == null ? '' : v; }
    function setChecked(id, v) { const el = document.getElementById(id); if(el) el.checked = !!v; }
    /* Checkbox group helpers (roles/accounts use checkboxes, not <select multiple>). */
    function setCheckboxGroup(name, vals) {
        const boxes = document.querySelectorAll('input[name="' + name + '"]');
        const valSet = {}; vals.forEach(v => valSet[v] = true);
        boxes.forEach(cb => { cb.checked = !!valSet[cb.value]; });
    }
    function getCheckboxGroup(name) {
        return Array.from(document.querySelectorAll('input[name="' + name + '"]:checked')).map(cb => cb.value);
    }
    function setMulti(id, vals) {
        const el = document.getElementById(id);
        if(!el) return;
        $$('#' + id + ' option').forEach(opt => { opt.selected = vals.indexOf(opt.value) !== -1; });
    }
    function esc(s) {
        if(s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function empty(v) { return v == null || v === '' || v === false || v === 0; }
    function empty2(v) { return v == null || v === '' || v === false; }
    /* Init triggers — supports 3 scenarios:
       1. Direct page load (DOMContentLoaded)
       2. ZenTao SPA soft-navigation (afterPageRender hook)
       3. Any other JS injection (MutationObserver on document.body) */
    function startWhenReady() {
        const editor = document.getElementById('workflowEditor');
        if(!editor || editor.dataset.inited) return;
        editor.dataset.inited = '1';
        init();
    }
    if(document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startWhenReady);
    } else {
        startWhenReady();
    }
    /* ZenTao SPA calls window.afterPageRender after injecting new page content. */
    if(typeof window.afterPageRender === 'undefined' || window.afterPageRender !== startWhenReady) {
        window.afterPageRender = startWhenReady;
    }
    /* Fallback: MutationObserver detects #workflowEditor appearance in any injection scenario. */
    if(!window.__statetransitionEditorObserver) {
        window.__statetransitionEditorObserver = new MutationObserver(() => {
            const editor = document.getElementById('workflowEditor');
            if(editor && !editor.dataset.inited) startWhenReady();
        });
        window.__statetransitionEditorObserver.observe(document.body, { childList: true, subtree: true });
    }
})();

<?php
declare(strict_types=1);
/**
 * The browse view file of statetransition module.
 *
 * Lists the 5 object types with their workflow state.
 * Big CTA card for entering the visual editor.
 */
namespace zin;

$manageURL  = $this->createLink('statetransition', 'manage', "objectType={$objectType}&productID={$productID}");
$resetURL   = $this->createLink('statetransition', 'reset',  "objectType={$objectType}&productID={$productID}");

/* Build scope dropdown HTML. */
$scopeOptions = '';
foreach($products as $id => $name)
{
    $selected = ((int)$id === (int)$productID) ? ' selected' : '';
    $url      = $this->createLink('statetransition', 'browse', "objectType={$objectType}&productID={$id}");
    $scopeOptions .= '<option value="' . htmlspecialchars($url) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
}
$scopeSelect = '<select class="form-control" style="display:inline-block;width:auto" onchange="if(this.value)window.location.href=this.value">' . $scopeOptions . '</select>';

/* Build the body content as a single HTML string, then wrap as Zin html() node
   so ZenTao's page wg picks it up as a child of #main. */
$stateBadge = $isDefault ? 'default' : ($enabled ? 'enabled' : 'disabled');
$stateLabel = htmlspecialchars($isDefault ? $lang->statetransition->stateDefault : ($enabled ? $lang->statetransition->stateEnabled : $lang->statetransition->stateDisabled));
$scopeLabel = $productID == 0 ? htmlspecialchars($lang->statetransition->globalScope) : htmlspecialchars($products[$productID] ?? "Product #{$productID}");

/* Build status rows. */
$statusRows = '';
foreach($definition['statuses'] ?? array() as $status)
{
    $label    = $status['label']['zh_cn'] ?? ($status['label']['en'] ?? $status['key']);
    $statusRows .= '<tr>'
        . '<td><code>' . htmlspecialchars($status['key']) . '</code></td>'
        . '<td>' . htmlspecialchars($label) . '</td>'
        . '<td>' . htmlspecialchars($lang->statetransition->categoryList[$status['category']] ?? $status['category']) . '</td>'
        . '<td><span class="color-dot" style="background:' . htmlspecialchars($status['color'] ?? '#999') . '"></span> ' . htmlspecialchars($status['color'] ?? '#999') . '</td>'
        . '<td>' . (!empty($status['isSystem']) ? $lang->YES : $lang->NO) . '</td>'
        . '<td>' . (!empty($status['isEntry']) ? $lang->YES : $lang->NO) . '</td>'
        . '</tr>';
}

/* Build transition rows. */
$transitionRows = '';
foreach($definition['transitions'] ?? array() as $tr)
{
    $actionLabel = $lang->statetransition->actionList[$tr['action']] ?? $tr['action'];
    $transitionRows .= '<tr>'
        . '<td><span class="action-chip">' . htmlspecialchars($actionLabel) . '</span></td>'
        . '<td>' . htmlspecialchars($tr['fromStatus']) . '</td>'
        . '<td>→</td>'
        . '<td>' . htmlspecialchars($tr['toStatus']) . '</td>'
        . '<td>' . (!empty($tr['requireComment']) ? $lang->YES : $lang->NO) . '</td>'
        . '<td>' . (empty($tr['enabled']) ? $lang->NO : $lang->YES) . '</td>'
        . '</tr>';
}

/* Reset button (only when not default). */
$resetBtn = $isDefault ? '' : '<button type="button" class="btn btn-lg statetransition-reset-btn" data-reset-url="' . htmlspecialchars($resetURL) . '" data-return-url="' . htmlspecialchars($this->createLink('statetransition', 'browse', "objectType={$objectType}&productID={$productID}")) . '"><i class="icon icon-refresh"></i> ' . htmlspecialchars($lang->statetransition->reset) . '</button>';

$bodyHTML = '<div class="statetransition-cta">'
    . '<div class="statetransition-cta-row">'
    .   '<div class="statetransition-cta-info">'
    .     '<h2>' . htmlspecialchars($lang->statetransition->objectTypeList[$objectType] ?? $objectType)
    .       '<span class="statetransition-state-badge state-' . $stateBadge . '">' . $stateLabel . '</span>'
    .     '</h2>'
    .     '<div class="statetransition-meta">'
    .       '<span><strong>' . htmlspecialchars($lang->statetransition->fieldScope) . ':</strong> ' . $scopeLabel . '</span>'
    .       '<span><strong>' . htmlspecialchars($lang->statetransition->fieldVersion) . ':</strong> v' . (int)$version . '</span>'
    .       '<span><strong>' . htmlspecialchars($lang->statetransition->fieldStatusCount) . ':</strong> ' . count($definition['statuses'] ?? array()) . '</span>'
    .       '<span><strong>' . htmlspecialchars($lang->statetransition->fieldTransitionCount) . ':</strong> ' . count($definition['transitions'] ?? array()) . '</span>'
    .     '</div>'
    .     '<div class="statetransition-scope-row"><label>' . htmlspecialchars($lang->statetransition->fieldScope) . ':</label> ' . $scopeSelect . '</div>'
    .   '</div>'
    .   '<div class="statetransition-cta-actions">'
    .     '<a class="btn btn-primary btn-lg" href="' . htmlspecialchars($manageURL) . '"><i class="icon icon-edit"></i> ' . htmlspecialchars($lang->statetransition->manage) . '</a>'
    .     $resetBtn
    .   '</div>'
    . '</div>'
    . '</div>'

    . '<div class="statetransition-flow panel">'
    .   '<div class="panel-heading"><strong>' . htmlspecialchars($lang->statetransition->flowDiagramTitle) . '</strong></div>'
    .   '<div class="panel-body"><div class="statetransition-mermaid" data-mermaid-source="' . htmlspecialchars($mermaidText, ENT_QUOTES) . '">' . htmlspecialchars($mermaidText) . '</div></div>'
    . '</div>'

    . '<div class="panel">'
    .   '<div class="panel-heading"><strong>' . htmlspecialchars($lang->statetransition->statuses) . '</strong></div>'
    .   '<div class="panel-body"><table class="table table-bordered"><thead><tr>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldStatusKey) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldStatusLabel) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldCategory) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldColor) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldIsSystem) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldIsEntry) . '</th>'
    .   '</tr></thead><tbody>' . $statusRows . '</tbody></table></div>'
    . '</div>'

    . '<div class="panel">'
    .   '<div class="panel-heading"><strong>' . htmlspecialchars($lang->statetransition->transitionsTitle) . '</strong></div>'
    .   '<div class="panel-body"><table class="table table-bordered"><thead><tr>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldAction) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldFromStatus) . '</th>'
    .     '<th>→</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldToStatus) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldRequireComment) . '</th>'
    .     '<th>' . htmlspecialchars($lang->statetransition->fieldEnabled) . '</th>'
    .   '</tr></thead><tbody>' . $transitionRows . '</tbody></table></div>'
    . '</div>';

/* Inline CSS for layout/colors. Pass via page wg's pageCSS block so it ends up in <head>. */
$css = <<<'CSS'
.statetransition-cta {
    background: linear-gradient(135deg, #eef5ff 0%, #f8fbff 100%);
    border: 1px solid #c7d8f5; border-radius: 8px; padding: 24px; margin: 16px 0;
}
.statetransition-cta-row { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.statetransition-cta-info h2 {
    margin: 0 0 12px; font-size: 22px; font-weight: 700; color: #1e3a8a;
    display: flex; align-items: center; gap: 12px;
}
.statetransition-state-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.statetransition-state-badge.state-default { background: #e2e8f0; color: #475569; }
.statetransition-state-badge.state-enabled { background: #dcfce7; color: #166534; }
.statetransition-state-badge.state-disabled { background: #fee2e2; color: #991b1b; }
.statetransition-meta { display: flex; gap: 20px; flex-wrap: wrap; color: #475569; font-size: 14px; }
.statetransition-meta strong { color: #1e293b; }
.statetransition-scope-row { margin-top: 12px; display: flex; align-items: center; gap: 10px; color: #475569; font-size: 14px; }
.statetransition-cta-actions { display: flex; flex-direction: column; gap: 10px; }
.statetransition-cta-actions .btn { min-width: 180px; font-size: 16px; padding: 10px 24px; }
.color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; border: 1px solid #cbd5e1; margin-right: 4px; }
.action-chip { padding: 2px 8px; border-radius: 10px; background: #eef3ff; color: #2468f2; font-size: 12px; font-weight: 500; }
.statetransition-flow .statetransition-mermaid { text-align: center; padding: 20px; }
.statetransition-flow .statetransition-mermaid svg { max-width: 100%; height: auto; }
CSS;

/* 1. Feature bar — Zin node, automatically goes into #mainMenu. */
/* Build objectType tabs dynamically (epic/requirement filtered when feature disabled). */
$objectTypeTabs = array();
foreach($this->config->statetransition->objectTypes as $type)
{
    $objectTypeTabs[] = li(setClass('nav-item'), a(set::href($this->createLink('statetransition', 'browse', "objectType={$type}&productID={$productID}")), $lang->statetransition->objectTypeList[$type] ?? $type));
}

featureBar
(
    set::current($objectType),
    set::linkParams("objectType={key}&productID={$productID}"),
    $objectTypeTabs
);

/* 2. Body content + page CSS as Zin nodes → become children of #main. */
html($bodyHTML);

/* Inline CSS via style block (also a Zin node). */
html('<style class="zin-page-css" data-id="statetransition-browse">' . $css . '</style>');

/* 3. Mermaid library + explicit init (startOnLoad doesn't fire if DOMContentLoaded already past). */
$mermaidWebPath = $this->config->webRoot . 'js/zui3/mermaid/mermaid.min.js';
$confirmResetJSON = json_encode($lang->statetransition->confirmReset, JSON_UNESCAPED_UNICODE);
$mermaidWebPathJSON = json_encode($mermaidWebPath, JSON_UNESCAPED_SLASHES);
html('<script>(function(){var seq=(window.__statetransitionBrowseMermaidSeq||0)+1;window.__statetransitionBrowseMermaidSeq=seq;function bindReset(){document.querySelectorAll(".statetransition-reset-btn").forEach(function(btn){if(btn.dataset.bound)return;btn.dataset.bound="1";btn.addEventListener("click",function(){if(!confirm(' . $confirmResetJSON . '))return;var body=new FormData();body.append("reset","1");fetch(btn.dataset.resetUrl,{method:"POST",body:body,headers:{"X-Requested-With":"XMLHttpRequest"}}).then(function(resp){return resp.json();}).then(function(result){if(result.result==="success"){window.location.href=btn.dataset.returnUrl;}else{alert(result.message||"恢复默认失败");}}).catch(function(e){alert("网络错误: "+e.message);});});});}function loadMermaid(done){if(typeof mermaid!=="undefined")return done();var id="statetransitionMermaidLib";var script=document.getElementById(id);if(script){script.addEventListener("load",done,{once:true});return;}script=document.createElement("script");script.id=id;script.src=' . $mermaidWebPathJSON . ';script.onload=done;document.head.appendChild(script);}function renderMermaid(){if(seq!==window.__statetransitionBrowseMermaidSeq)return;var nodes=Array.from(document.querySelectorAll(".statetransition-flow .statetransition-mermaid"));if(!nodes.length)return;if(typeof mermaid==="undefined"){setTimeout(renderMermaid,50);return;}try{mermaid.initialize({startOnLoad:false,securityLevel:"loose"});nodes.forEach(function(node){node.classList.remove("mermaid");node.textContent=node.dataset.mermaidSource||node.textContent;node.removeAttribute("data-processed");});var promise=mermaid.run?mermaid.run({nodes:nodes}):new Promise(function(resolve){mermaid.init(undefined,nodes);resolve();});promise.catch(function(e){if(seq!==window.__statetransitionBrowseMermaidSeq)return;console.warn("[statetransition] mermaid render error:",e);nodes.forEach(function(node){node.innerHTML="<div class=\"workflow-hint\">流程图渲染失败，请检查定义。</div>";});});}catch(e){console.warn("[statetransition] mermaid render error:",e);}}function schedule(){bindReset();loadMermaid(function(){setTimeout(renderMermaid,0);});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",schedule,{once:true});}else{schedule();}})();</script>');

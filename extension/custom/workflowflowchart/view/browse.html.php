<?php
$definitionJSON = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$defaultsJSON   = json_encode($this->workflowflowchart->getDefaultDefinition($objectType), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$statusJSON     = json_encode($statusList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$actionJSON     = json_encode($actionList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$entryJSON      = json_encode($this->workflowflowchart->getEntryStates($objectType), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$escape         = function($value){return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');};
$mermaidSource  = $this->workflowflowchart->renderMermaid($objectType, $definition);
$nodes          = array();
foreach($definition['nodes'] as $node)
{
    $nodes[$node['id']] = array(
        'id' => $node['id'],
        'label' => isset($statusList[$node['status']]) ? $statusList[$node['status']] : $node['label']
    );
}
$enabledEdges = array_values(array_filter($definition['edges'], function($edge){return !isset($edge['enabled']) || $edge['enabled'];}));
$edgesBySource = array();
foreach($enabledEdges as $edge)
{
    if(!isset($edgesBySource[$edge['source']])) $edgesBySource[$edge['source']] = array();
    $edgesBySource[$edge['source']][] = $edge;
}
?>
<style>
body > #main:has(#mainContent:empty) {display:none; min-height:0;}
#mainContent:empty {display:none;}
#main > .container {padding-top:0;}
.workflow-page {display:flex; flex-direction:column; margin:0; background:#fff;}
.workflow-header {display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 20px; border-bottom:1px solid #d8dee8;}
.workflow-tabs {display:flex; gap:4px; flex-wrap:wrap;}
.workflow-tab {padding:7px 12px; color:#44546a; border-bottom:2px solid transparent; text-decoration:none;}
.workflow-tab.active {color:#2468f2; border-color:#2468f2; font-weight:600;}
.workflow-actions {display:flex; align-items:center; gap:10px; flex-wrap:wrap;}
.workflow-body {display:grid; grid-template-columns:minmax(0,1fr) 340px; min-height:0;}
.workflow-main {min-width:0; overflow:auto; padding:16px 20px; background:#f6f8fb;}
.workflow-mermaid-panel {margin-bottom:14px; border:1px solid #d8dee8; border-radius:6px; background:#fff; overflow:auto;}
.workflow-mermaid {min-height:280px; padding:16px; text-align:center;}
.workflow-mermaid svg {max-width:100%; height:auto;}
.workflow-mermaid [data-edge-id] {cursor:pointer; transition:opacity .16s ease, stroke-width .16s ease;}
.workflow-mermaid path.workflow-mermaid-edge-hit {fill:none !important;stroke:transparent !important;stroke-width:16px !important;vector-effect:non-scaling-stroke;pointer-events:stroke;opacity:1 !important;marker-start:none !important;marker-end:none !important;}
.workflow-mermaid .workflow-mermaid-dimmed {opacity:.22;}
.workflow-mermaid .workflow-mermaid-edge-active {outline:none;}
.workflow-mermaid .workflow-mermaid-state-active {opacity:1;}
.workflow-mermaid path.workflow-mermaid-edge-active {opacity:1; stroke:#2468f2 !important; stroke-width:3px !important; filter:drop-shadow(0 1px 2px rgba(36,104,242,.25));}
.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active {opacity:1;}
.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active rect,.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active .label rect {fill:#dbeafe !important; stroke:#2468f2 !important; opacity:.95 !important;}
.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active text,.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active span,.workflow-mermaid .edgeLabel.workflow-mermaid-edge-active p {color:#1d4ed8 !important; fill:#1d4ed8 !important; font-weight:600;}
.workflow-mermaid .workflow-mermaid-state-active rect,.workflow-mermaid .workflow-mermaid-state-active polygon,.workflow-mermaid .workflow-mermaid-state-active circle {stroke:#2468f2 !important; stroke-width:2px !important; fill:#eef5ff !important;}
.workflow-mermaid .workflow-mermaid-state-active text,.workflow-mermaid .workflow-mermaid-state-active span,.workflow-mermaid .workflow-mermaid-state-active p {color:#1d4ed8 !important; fill:#1d4ed8 !important; font-weight:600;}
.workflow-section {border:1px solid #d8dee8; border-radius:6px; margin-bottom:14px; overflow:hidden;}
.workflow-section-head {display:flex; align-items:center; justify-content:space-between; min-height:38px; padding:9px 12px; border-bottom:1px solid #d8dee8; color:#24364f; font-weight:700;}
.workflow-node-section {background:#f8fbff;}
.workflow-node-section .workflow-section-head {background:#eef5ff;}
.workflow-rule-section {background:#fff;}
.workflow-rule-section .workflow-section-head {background:#f7f8fa;}
.workflow-board {display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; padding:10px;}
.workflow-column {border:1px solid #cfd8e6; border-radius:6px; background:#fbfdff; min-width:0;}
.workflow-node {display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-bottom:1px solid #e7ebf2;color:#24364f;font-size:14px;font-weight:600;}
.workflow-route {display:flex; align-items:center; justify-content:space-between; gap:8px; width:calc(100% - 16px); margin:8px; padding:8px; border:1px solid #e2e7f0; border-radius:4px; background:#fff; color:#26364d; text-align:left; cursor:pointer;}
.workflow-route.active {border-color:#2468f2; box-shadow:0 0 0 2px rgba(36,104,242,.12);}
.workflow-target {font-weight:600;}
.workflow-list {display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; padding:10px; background:#fff;}
.workflow-transition {display:flex; align-items:center; gap:8px; min-height:46px; padding:10px 12px; border:1px solid #d8dee8; border-radius:6px; background:#fff; cursor:pointer;}
.workflow-transition.disabled {opacity:.52; border-style:dashed;}
.workflow-transition.active {border-color:#2468f2; box-shadow:0 0 0 2px rgba(36,104,242,.12);}
.workflow-state {font-weight:600; color:#26364d;}
.workflow-action-label {padding:2px 7px; border-radius:10px; background:#eef3ff; color:#2468f2; font-size:12px; white-space:nowrap;}
.workflow-arrow {color:#8795aa;}
.workflow-panel {overflow:auto; border-left:1px solid #d8dee8; padding:16px; background:#fff;}
.workflow-panel h3 {font-size:15px; margin:0 0 14px;}
.workflow-field {margin-bottom:13px;}
.workflow-field label {display:block; font-weight:600; margin-bottom:5px; color:#3f4b5f;}
.workflow-field select,.workflow-field input[type=text],.workflow-field textarea {width:100%; border:1px solid #cbd3df; border-radius:4px; padding:7px 8px; background:#fff;}
.workflow-field select[multiple] {min-height:84px;}
.workflow-field textarea {min-height:76px; resize:vertical;}
.workflow-hint {font-size:12px; line-height:1.5; color:#758198; margin-top:5px;}
.workflow-add-grid {display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;}
.workflow-add-grid .full {grid-column:1/-1;}
.workflow-rule-empty {padding:34px 10px; text-align:center; color:#758198;}
.workflow-danger {color:#c73535;}
@media (max-width:900px){.workflow-header{align-items:flex-start;flex-direction:column}.workflow-body{grid-template-columns:1fr}.workflow-main{padding:12px}.workflow-panel{border-left:0;border-top:1px solid #d8dee8}.workflow-actions{width:100%}.workflow-board{grid-template-columns:1fr}}
</style>
<div class="workflow-page">
  <div class="workflow-header">
    <div class="workflow-tabs">
      <?php foreach($objectTypes as $type): $label = $this->lang->workflowflowchart->objectTypeList[$type];?>
      <a class="workflow-tab <?php if($type == $objectType) echo 'active';?>" href="<?php echo $this->createLink('workflowflowchart', 'browse', "objectType=$type&mode=" . ($editable ? 'edit' : 'view') . '&_single=1&zin=1');?>" onclick="window.location.href=this.href; return false;"><?php echo $escape($label);?></a>
      <?php endforeach;?>
    </div>
    <div class="workflow-actions">
      <?php if($editable):?>
      <label title="<?php echo $escape($this->lang->workflowflowchart->enabledTip);?>"><input type="checkbox" id="workflowEnabled" <?php if(!empty($definition['enabled'])) echo 'checked';?>> <?php echo $escape($this->lang->workflowflowchart->enabled);?></label>
      <button type="button" class="btn" id="resetWorkflow"><i class="icon icon-refresh"></i> <?php echo $escape($this->lang->workflowflowchart->reset);?></button>
      <button type="button" class="btn primary" id="saveWorkflow"><i class="icon icon-save"></i> <?php echo $escape($this->lang->workflowflowchart->save);?></button>
      <?php else:?>
      <span class="label secondary-pale"><?php echo $escape($this->lang->workflowflowchart->readonly);?></span>
      <?php endif;?>
    </div>
  </div>
  <div class="workflow-body">
    <main class="workflow-main">
      <section class="workflow-mermaid-panel" aria-label="<?php echo $escape($this->lang->workflowflowchart->common);?>">
        <div class="workflow-mermaid mermaid" id="workflowMermaid" data-entry-states="<?php echo $escape(implode(',', $this->workflowflowchart->getEntryStates($objectType)));?>"><?php echo $escape($mermaidSource);?></div>
      </section>
      <section class="workflow-section workflow-node-section">
        <div class="workflow-section-head"><?php echo $escape($this->lang->workflowflowchart->nodeMatrix);?></div>
        <div class="workflow-board" id="workflowBoard" aria-label="<?php echo $escape($this->lang->workflowflowchart->nodeMatrix);?>">
          <?php foreach($nodes as $node):?>
          <section class="workflow-column" data-status="<?php echo $escape($node['id']);?>">
            <div class="workflow-node <?php echo $escape($node['id']);?>"><?php echo $escape($node['label']);?></div>
            <?php $sourceEdges = isset($edgesBySource[$node['id']]) ? $edgesBySource[$node['id']] : array();?>
            <?php if(!$sourceEdges):?><div class="workflow-hint"><?php echo $escape($this->lang->workflowflowchart->flowEmpty);?></div><?php endif;?>
            <?php foreach($sourceEdges as $edge): $label = $edge['label'] ?: (isset($actionList[$edge['action']]) ? $actionList[$edge['action']] : $edge['action']);?>
            <button type="button" class="workflow-route" data-edge="<?php echo $escape($edge['id']);?>">
              <span class="workflow-action-label"><?php echo $escape($label);?></span>
              <span class="workflow-arrow">→</span>
              <span class="workflow-target"><?php echo $escape(isset($nodes[$edge['target']]) ? $nodes[$edge['target']]['label'] : $edge['target']);?></span>
            </button>
            <?php endforeach;?>
          </section>
          <?php endforeach;?>
        </div>
      </section>
      <section class="workflow-section workflow-rule-section">
        <div class="workflow-section-head"><?php echo $escape($this->lang->workflowflowchart->ruleMatrix);?></div>
        <div class="workflow-list" id="workflowList" aria-label="<?php echo $escape($this->lang->workflowflowchart->ruleMatrix);?>">
          <?php foreach($definition['edges'] as $edge): $label = $edge['label'] ?: (isset($actionList[$edge['action']]) ? $actionList[$edge['action']] : $edge['action']);?>
          <button type="button" class="workflow-transition <?php if(isset($edge['enabled']) && !$edge['enabled']) echo 'disabled';?>" data-edge="<?php echo $escape($edge['id']);?>">
            <span class="workflow-state"><?php echo $escape(isset($nodes[$edge['source']]) ? $nodes[$edge['source']]['label'] : $edge['source']);?></span>
            <span class="workflow-arrow">→</span>
            <span class="workflow-action-label"><?php echo $escape($label);?></span>
            <span class="workflow-arrow">→</span>
            <span class="workflow-state"><?php echo $escape(isset($nodes[$edge['target']]) ? $nodes[$edge['target']]['label'] : $edge['target']);?></span>
          </button>
          <?php endforeach;?>
        </div>
      </section>
    </main>
    <aside class="workflow-panel">
      <?php if($editable):?>
      <section>
        <h3><?php echo $escape($this->lang->workflowflowchart->addNode);?></h3>
        <div class="workflow-add-grid">
          <input id="newNodeID" placeholder="<?php echo $escape($this->lang->workflowflowchart->nodeID);?>">
          <input id="newNodeLabel" placeholder="<?php echo $escape($this->lang->workflowflowchart->nodeLabel);?>">
          <button type="button" class="btn full" id="addNode"><i class="icon icon-plus"></i> <?php echo $escape($this->lang->workflowflowchart->addNode);?></button>
        </div>
      </section>
      <hr>
      <section>
        <h3><?php echo $escape($this->lang->workflowflowchart->addTransition);?></h3>
        <div class="workflow-add-grid">
          <select id="newSource"><?php foreach($statusList as $key => $label) echo '<option value="' . $escape($key) . '">' . $escape($label) . '</option>';?></select>
          <select id="newTarget"><?php foreach($statusList as $key => $label) echo '<option value="' . $escape($key) . '">' . $escape($label) . '</option>';?></select>
          <select class="full" id="newAction"><?php foreach($actionList as $key => $label) echo '<option value="' . $escape($key) . '">' . $escape($label) . '</option>';?></select>
          <input class="full" id="newTransitionLabel" maxlength="100" placeholder="<?php echo $escape($this->lang->workflowflowchart->transitionName);?>">
          <button type="button" class="btn full" id="addTransition"><i class="icon icon-plus"></i> <?php echo $escape($this->lang->workflowflowchart->addTransition);?></button>
        </div>
      </section>
      <hr>
      <section id="ruleEditor" class="hidden">
        <h3><?php echo $escape($this->lang->workflowflowchart->transitionRule);?></h3>
        <div class="workflow-field"><label><?php echo $escape($this->lang->workflowflowchart->label);?></label><input type="text" id="edgeLabel"></div>
        <div class="workflow-field"><label><?php echo $escape($this->lang->workflowflowchart->roles);?></label><select multiple id="edgeRoles"><?php foreach($roleList as $key => $label) if($key !== '') echo '<option value="' . $escape($key) . '">' . $escape($label) . '</option>';?></select></div>
        <div class="workflow-field"><label><?php echo $escape($this->lang->workflowflowchart->accounts);?></label><select multiple id="edgeAccounts"><?php foreach($users as $key => $label) echo '<option value="' . $escape($key) . '">' . $escape($label) . '</option>';?></select><div class="workflow-hint"><?php echo $escape($this->lang->workflowflowchart->allActors);?></div></div>
        <div class="workflow-field"><label><input type="checkbox" id="edgeRequireComment"> <?php echo $escape($this->lang->workflowflowchart->requireComment);?></label></div>
        <div class="workflow-field"><label><input type="checkbox" id="edgeEnabled"> <?php echo $escape($this->lang->workflowflowchart->edgeEnabled);?></label></div>
        <div class="workflow-field"><label><?php echo $escape($this->lang->workflowflowchart->description);?></label><textarea id="edgeDescription"></textarea></div>
        <button type="button" class="btn workflow-danger" id="deleteTransition"><i class="icon icon-trash"></i> <?php echo $escape($this->lang->workflowflowchart->deleteEdge);?></button>
      </section>
      <div class="workflow-rule-empty" id="ruleEmpty"><?php echo $escape($this->lang->workflowflowchart->selectEdge);?></div>
      <?php else:?>
      <h3><?php echo $escape($this->lang->workflowflowchart->transitionRule);?></h3>
      <div id="readonlyRules"></div>
      <?php endif;?>
    </aside>
  </div>
</div>
<script src="<?php echo $this->app->getWebRoot();?>js/zui3/mermaid/mermaid.min.js"></script>
<script>
(function(){
var definition = <?php echo $definitionJSON;?>;
var defaults = <?php echo $defaultsJSON;?>;
var statusLabels = <?php echo $statusJSON;?>;
var actionLabels = <?php echo $actionJSON;?>;
var entryStates = <?php echo $entryJSON;?>;
var editable = <?php echo $editable ? 'true' : 'false';?>;
var selectedEdge = null;
var mermaidRenderID = 0;
var mermaidQueue = Promise.resolve();

function escapeHtml(v){var d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
function mermaidStateID(status){var id=String(status).replace(/[^A-Za-z0-9_]/g,'_');return !id || /^[0-9]/.test(id) ? 'state_' + id : id;}
function mermaidLabel(label){return String(label || '').replace(/\\/g,'\\\\').replace(/"/g,'\\"').replace(/\s+/g,' ').trim();}
function buildMermaidSource(){
  var lines = ['stateDiagram-v2'];
  var nodes = nodeLayout();
  var ids = {};
  Object.keys(nodes).forEach(function(status){
    ids[status] = mermaidStateID(status);
    lines.push('    state "' + mermaidLabel(nodes[status].label) + '" as ' + ids[status]);
  });
  entryStates.forEach(function(status){if(ids[status]) lines.push('    [*] --> ' + ids[status]);});
  buildMermaidTransitions(ids).forEach(function(t){
    lines.push('    ' + ids[t.source] + ' --> ' + ids[t.target] + ': ' + mermaidLabel(t.labels.join(' / ')));
  });
  return lines.join('\n');
}
function buildMermaidTransitions(ids){
  var transitions = {};
  definition.edges.forEach(function(e){
    if(e.enabled === false || !ids[e.source] || !ids[e.target]) return;
    var key = e.source + '\t' + e.target;
    var label = e.label || actionLabels[e.action] || e.action;
    if(!transitions[key]) transitions[key] = {source:e.source,target:e.target,labels:[],edgeIDs:[]};
    if(transitions[key].labels.indexOf(label) === -1) transitions[key].labels.push(label);
    transitions[key].edgeIDs.push(e.id);
  });
  return Object.keys(transitions).map(function(key){return transitions[key];});
}
function currentMermaidTransitions(){
  var nodes = nodeLayout();
  var ids = {};
  Object.keys(nodes).forEach(function(status){ids[status] = mermaidStateID(status);});
  return buildMermaidTransitions(ids);
}
function renderMermaid(){
  var root = document.getElementById('workflowMermaid');
  if(!root) return;
  var source = buildMermaidSource();
  var transitions = currentMermaidTransitions();
  var renderID = ++mermaidRenderID;
  root.removeAttribute('data-processed');
  root.textContent = source;
  if(!window.mermaid) return;
  window.mermaid.initialize({startOnLoad:false, securityLevel:'loose'});
  mermaidQueue = mermaidQueue.catch(function(){}).then(function(){
    if(renderID !== mermaidRenderID) return;
    return window.mermaid.render('workflowMermaidSvg' + renderID, source).then(function(result){
      if(renderID !== mermaidRenderID) return;
      root.innerHTML = result.svg;
      if(result.bindFunctions) result.bindFunctions(root);
      bindMermaidStates(root);
      bindMermaidEdges(root, transitions);
      updateMermaidSelection();
    }).catch(function(error){
      if(renderID === mermaidRenderID) root.textContent = source;
      if(window.console) console.error(error);
    });
  });
}
function bindMermaidEdges(root, transitions){
  var labels = Array.prototype.slice.call(root.querySelectorAll('g.edgeLabel,.edgeLabel')).filter(function(node, index, list){return list.indexOf(node) === index;});
  var paths = Array.prototype.slice.call(root.querySelectorAll('path.transition')).filter(function(node, index, list){return list.indexOf(node) === index;});
  var stateBoxes = getMermaidStateBoxes(root);
  var assigned = mapMermaidPaths(paths, transitions, stateBoxes);
  function bind(node, transition){
    if(!node || !transition || !transition.edgeIDs.length) return;
    var edgeID = transition.edgeIDs[0];
    node.setAttribute('data-edge-id', edgeID);
    node.setAttribute('data-edge-ids', transition.edgeIDs.join(' '));
    node.setAttribute('data-source', transition.source);
    node.setAttribute('data-target', transition.target);
    node.setAttribute('role', 'button');
    node.setAttribute('tabindex', '0');
    node.onclick = function(event){event.preventDefault();event.stopPropagation();toggleMermaidTransition(transition, node);};
    node.onkeydown = function(event){if(event.key === 'Enter' || event.key === ' '){event.preventDefault();toggleMermaidTransition(transition, node);}};
  }
  assigned.forEach(function(item){
    bind(item.path, item.transition);
    var hitPath=item.path.cloneNode(false);
    hitPath.removeAttribute('id');
    hitPath.removeAttribute('class');
    hitPath.setAttribute('class','workflow-mermaid-edge-hit');
    hitPath.removeAttribute('marker-start');
    hitPath.removeAttribute('marker-end');
    item.path.parentNode.insertBefore(hitPath,item.path.nextSibling);
    bind(hitPath,item.transition);
  });
  mapMermaidLabels(labels, assigned).forEach(function(item){
    bind(item.label, item.transition);
  });
}
function getMermaidStateBoxes(root){
  var boxes = {};
  Array.prototype.forEach.call(root.querySelectorAll('[data-state-id]'), function(node){
    try{boxes[node.getAttribute('data-state-id')] = node.getBoundingClientRect();}catch(error){}
  });
  return boxes;
}
function pointToBoxDistance(point, box){
  var left = typeof box.left === 'number' ? box.left : box.x;
  var top = typeof box.top === 'number' ? box.top : box.y;
  var width = box.width;
  var height = box.height;
  var dx = point.x < left ? left - point.x : (point.x > left + width ? point.x - left - width : 0);
  var dy = point.y < top ? top - point.y : (point.y > top + height ? point.y - top - height : 0);
  return Math.sqrt(dx * dx + dy * dy);
}
function pathPointToScreen(path, point){
  var matrix = path.getScreenCTM();
  if(!matrix) return point;
  var svg = path.ownerSVGElement;
  var svgPoint = svg.createSVGPoint();
  svgPoint.x = point.x;
  svgPoint.y = point.y;
  return svgPoint.matrixTransform(matrix);
}
function pathToTransitionDistance(path, transition, stateBoxes){
  if(!stateBoxes[transition.source] || !stateBoxes[transition.target]) return Number.MAX_VALUE;
  try
  {
    var start = pathPointToScreen(path, path.getPointAtLength(0));
    var end   = pathPointToScreen(path, path.getPointAtLength(path.getTotalLength()));
    return pointToBoxDistance(start, stateBoxes[transition.source]) + pointToBoxDistance(end, stateBoxes[transition.target]);
  }
  catch(error)
  {
    return Number.MAX_VALUE;
  }
}
function mapMermaidPaths(paths, transitions, stateBoxes){
  var candidates = [];
  paths.forEach(function(path, pathIndex){
    transitions.forEach(function(transition, transitionIndex){
      candidates.push({path:path, pathIndex:pathIndex, transition:transition, transitionIndex:transitionIndex, score:pathToTransitionDistance(path, transition, stateBoxes)});
    });
  });
  candidates.sort(function(a, b){return a.score - b.score;});
  var usedPaths = {};
  var usedTransitions = {};
  var assigned = [];
  candidates.forEach(function(candidate){
    if(candidate.score === Number.MAX_VALUE || usedPaths[candidate.pathIndex] || usedTransitions[candidate.transitionIndex]) return;
    usedPaths[candidate.pathIndex] = true;
    usedTransitions[candidate.transitionIndex] = true;
    assigned.push({path:candidate.path, transition:candidate.transition});
  });
  return assigned;
}
function labelToPathDistance(label, path){
  try
  {
    var labelBox = label.getBoundingClientRect();
    var labelPoint = {x:labelBox.left + labelBox.width / 2, y:labelBox.top + labelBox.height / 2};
    var pathPoint = pathPointToScreen(path, path.getPointAtLength(path.getTotalLength() / 2));
    var dx = labelPoint.x - pathPoint.x;
    var dy = labelPoint.y - pathPoint.y;
    return Math.sqrt(dx * dx + dy * dy);
  }
  catch(error)
  {
    return Number.MAX_VALUE;
  }
}
function mapMermaidLabels(labels, assignedPaths){
  var candidates = [];
  labels.forEach(function(label, labelIndex){
    assignedPaths.forEach(function(item, pathIndex){
      candidates.push({label:label, labelIndex:labelIndex, transition:item.transition, pathIndex:pathIndex, score:labelToPathDistance(label, item.path)});
    });
  });
  candidates.sort(function(a, b){return a.score - b.score;});
  var usedLabels = {};
  var usedPaths = {};
  var assigned = [];
  candidates.forEach(function(candidate){
    if(candidate.score === Number.MAX_VALUE || usedLabels[candidate.labelIndex] || usedPaths[candidate.pathIndex]) return;
    usedLabels[candidate.labelIndex] = true;
    usedPaths[candidate.pathIndex] = true;
    assigned.push({label:candidate.label, transition:candidate.transition});
  });
  return assigned;
}
function bindMermaidStates(root){
  var labels = nodeLayout();
  var nodes = Array.prototype.slice.call(root.querySelectorAll('g.node,g.stateGroup,g.statediagram-state')).filter(function(node, index, list){return list.indexOf(node) === index;});
  Object.keys(labels).forEach(function(status){
    var label = String(labels[status].label || '').replace(/\s+/g, '');
    if(!label) return;
    var match = nodes.find(function(node){return !node.getAttribute('data-state-id') && node.textContent.replace(/\s+/g, '').indexOf(label) !== -1;});
    if(match) match.setAttribute('data-state-id', status);
  });
}
function toggleMermaidTransition(transition, node){
  var edgeIDs=transition.edgeIDs.filter(function(edgeID){return definition.edges.some(function(edge){return edge.id===edgeID;});});
  if(!edgeIDs.length)return;
  if(edgeIDs.indexOf(selectedEdge)!==-1){selectEdge(null);return;}
  var edgeID=edgeIDs[0];
  Array.prototype.forEach.call(document.querySelectorAll('.workflow-mermaid [data-source="'+transition.source+'"][data-target="'+transition.target+'"]'),function(item){item.setAttribute('data-edge-id',edgeID);item.setAttribute('data-edge-ids',edgeIDs.join(' '));});
  selectEdge(edgeID);
}
function toggleRuleEdge(edgeID){
  selectEdge(selectedEdge === edgeID ? null : edgeID);
}
function updateMermaidSelection(){
  var root = document.getElementById('workflowMermaid');
  if(!root) return;
  root.classList.toggle('has-selection', !!selectedEdge);
  Array.prototype.forEach.call(root.querySelectorAll('.workflow-mermaid-edge-active,.workflow-mermaid-state-active,.workflow-mermaid-dimmed'), function(node){node.classList.remove('workflow-mermaid-edge-active','workflow-mermaid-state-active','workflow-mermaid-dimmed');});
  if(!selectedEdge) return;
  var edge = definition.edges.find(function(e){return e.id === selectedEdge;});
  if(!edge) return;
  Array.prototype.forEach.call(root.querySelectorAll('[data-source="'+edge.source+'"][data-target="'+edge.target+'"]'), function(node){node.classList.add('workflow-mermaid-edge-active');});
  Array.prototype.forEach.call(root.querySelectorAll('[data-state-id="'+edge.source+'"],[data-state-id="'+edge.target+'"]'), function(node){node.classList.add('workflow-mermaid-state-active');});
  Array.prototype.forEach.call(root.querySelectorAll('[data-edge-id],[data-state-id]'), function(node){
    if(!node.classList.contains('workflow-mermaid-edge-active') && !node.classList.contains('workflow-mermaid-state-active')) node.classList.add('workflow-mermaid-dimmed');
  });
}
function nodeLayout(){
  var nodes = {};
  definition.nodes.forEach(function(n){nodes[n.id] = {id:n.id,label:statusLabels[n.status] || n.label || n.status};});
  return nodes;
}
function nodeLabel(id){var node=definition.nodes.find(function(item){return item.id===id;});return node ? (statusLabels[node.status] || node.label || node.status) : id;}
function refreshNodeOptions(){if(!editable)return;['newSource','newTarget'].forEach(function(selectID){var select=document.getElementById(selectID),value=select.value;select.innerHTML='';definition.nodes.forEach(function(node){var option=document.createElement('option');option.value=node.id;option.textContent=nodeLabel(node.id);select.appendChild(option);});if(Array.from(select.options).some(function(option){return option.value===value;}))select.value=value;});}
function deleteNode(id){if(entryStates.indexOf(id)!==-1){zui.Modal.alert({message:<?php echo json_encode($this->lang->workflowflowchart->error->deleteEntryNode);?>});return;}definition.nodes=definition.nodes.filter(function(node){return node.id!==id;});definition.edges=definition.edges.filter(function(edge){return edge.source!==id&&edge.target!==id;});if(selectedEdge&&!definition.edges.some(function(edge){return edge.id===selectedEdge;}))selectEdge(null);render();}
function renderBoard(){
  var nodes = nodeLayout();
  var root = document.getElementById('workflowBoard');
  root.innerHTML = '';
  Object.keys(nodes).forEach(function(id){
    var n = nodes[id];
    var column = document.createElement('section');
    column.className = 'workflow-column';
    column.setAttribute('data-status', id);
    column.innerHTML = '<div class="workflow-node '+escapeHtml(id)+'"><span>'+escapeHtml(n.label)+'</span>'+(editable&&entryStates.indexOf(id)===-1?'<button type="button" class="btn ghost square size-sm workflow-delete-node" title="<?php echo $escape($this->lang->workflowflowchart->deleteNode);?>"><i class="icon icon-trash"></i></button>':'')+'</div>';
    var deleteButton=column.querySelector('.workflow-delete-node');if(deleteButton)deleteButton.onclick=function(){deleteNode(id);};
    var sourceEdges = definition.edges.filter(function(e){return e.enabled !== false && e.source === id;});
    if(!sourceEdges.length)
    {
      var empty = document.createElement('div');
      empty.className = 'workflow-hint';
      empty.textContent = <?php echo json_encode($this->lang->workflowflowchart->flowEmpty);?>;
      column.appendChild(empty);
    }
    sourceEdges.forEach(function(e){
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'workflow-route' + (e.id === selectedEdge ? ' active' : '');
      button.setAttribute('data-edge', e.id);
      button.innerHTML = '<span class="workflow-action-label">'+escapeHtml(e.label || actionLabels[e.action] || e.action)+'</span><span class="workflow-arrow">→</span><span class="workflow-target">'+escapeHtml(nodeLabel(e.target))+'</span>';
      button.onclick = function(){toggleRuleEdge(e.id);};
      column.appendChild(button);
    });
    root.appendChild(column);
  });
}
function renderList(){
  var root = document.getElementById('workflowList');
  root.innerHTML = '';
  definition.edges.forEach(function(e){
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'workflow-transition' + (e.enabled === false ? ' disabled' : '') + (e.id === selectedEdge ? ' active' : '');
    button.setAttribute('data-edge', e.id);
    button.innerHTML = '<span class="workflow-state">'+escapeHtml(nodeLabel(e.source))+'</span><span class="workflow-arrow">→</span><span class="workflow-action-label">'+escapeHtml(e.label || actionLabels[e.action] || e.action)+'</span><span class="workflow-arrow">→</span><span class="workflow-state">'+escapeHtml(nodeLabel(e.target))+'</span>';
    button.onclick = function(){toggleRuleEdge(e.id);};
    root.appendChild(button);
  });
  renderReadonlyRules();
}
function render(){refreshNodeOptions();renderMermaid();renderBoard();renderList();}
function selectEdge(id){selectedEdge=id;if(!editable){renderBoard();renderList();updateMermaidSelection();return;}var edge=definition.edges.find(function(e){return e.id===id;});document.getElementById('ruleEditor').classList.toggle('hidden',!edge);document.getElementById('ruleEmpty').classList.toggle('hidden',!!edge);if(!edge){renderBoard();renderList();updateMermaidSelection();return;}setValue('edgeLabel',edge.label||'');setMulti('edgeRoles',edge.roles||[]);setMulti('edgeAccounts',edge.accounts||[]);setChecked('edgeRequireComment',!!edge.requireComment);setValue('edgeDescription',edge.description||'');setChecked('edgeEnabled',edge.enabled!==false);renderBoard();renderList();updateMermaidSelection();}
function setValue(id,value){document.getElementById(id).value=value;} function setChecked(id,value){document.getElementById(id).checked=value;} function setMulti(id,values){Array.prototype.forEach.call(document.getElementById(id).options,function(o){o.selected=values.indexOf(o.value)!==-1;});} function getMulti(id){return Array.prototype.filter.call(document.getElementById(id).options,function(o){return o.selected;}).map(function(o){return o.value;});}
function updateEdge(){var edge=definition.edges.find(function(e){return e.id===selectedEdge;});if(!edge)return;edge.label=document.getElementById('edgeLabel').value.trim()||actionLabels[edge.action]||edge.action;edge.roles=getMulti('edgeRoles');edge.accounts=getMulti('edgeAccounts');edge.requireComment=document.getElementById('edgeRequireComment').checked;edge.enabled=document.getElementById('edgeEnabled').checked;edge.description=document.getElementById('edgeDescription').value.trim();render();}
function renderReadonlyRules(){if(editable)return;var root=document.getElementById('readonlyRules');root.innerHTML='';definition.edges.filter(function(e){return e.enabled!==false;}).forEach(function(e){var item=document.createElement('div');item.className='workflow-field';var actors=[];if(e.roles&&e.roles.length)actors.push(e.roles.join(', '));if(e.accounts&&e.accounts.length)actors.push(e.accounts.join(', '));item.innerHTML='<strong>'+escapeHtml(nodeLabel(e.source))+' → '+escapeHtml(nodeLabel(e.target))+'</strong><div class="workflow-hint">'+escapeHtml(e.label||actionLabels[e.action]||e.action)+(actors.length?' · '+escapeHtml(actors.join(' / ')):'')+(e.requireComment?' · <?php echo addslashes($this->lang->workflowflowchart->requireComment);?>':'')+'</div>';root.appendChild(item);});}
if(editable){
  ['edgeLabel','edgeRoles','edgeAccounts','edgeRequireComment','edgeEnabled','edgeDescription'].forEach(function(id){document.getElementById(id).addEventListener('change',updateEdge);});
  document.getElementById('addNode').onclick=function(){var id=document.getElementById('newNodeID').value.trim(),label=document.getElementById('newNodeLabel').value.trim();if(!/^[a-z][a-z0-9_]{1,29}$/.test(id)||!label){zui.Modal.alert({message:<?php echo json_encode($this->lang->workflowflowchart->error->invalidNewNode);?>});return;}if(definition.nodes.some(function(node){return node.id===id;})){zui.Modal.alert({message:<?php echo json_encode($this->lang->workflowflowchart->error->duplicateNode);?>});return;}definition.nodes.push({id:id,status:id,label:label,x:0,y:0});document.getElementById('newNodeID').value='';document.getElementById('newNodeLabel').value='';render();};
  document.getElementById('addTransition').onclick=function(){var source=document.getElementById('newSource').value,target=document.getElementById('newTarget').value,action=document.getElementById('newAction').value,label=document.getElementById('newTransitionLabel').value.trim()||actionLabels[action]||action,id=source+'-'+target+'-'+action+'-'+Date.now();definition.edges.push({id:id,source:source,target:target,action:action,label:label,roles:[],accounts:[],requireComment:false,enabled:true,description:''});document.getElementById('newTransitionLabel').value='';render();selectEdge(id);};
  document.getElementById('deleteTransition').onclick=function(){definition.edges=definition.edges.filter(function(e){return e.id!==selectedEdge;});selectEdge(null);render();};
  document.getElementById('resetWorkflow').onclick=function(){definition=JSON.parse(JSON.stringify(defaults));document.getElementById('workflowEnabled').checked=definition.enabled;selectEdge(null);render();};
  document.getElementById('saveWorkflow').onclick=function(){definition.enabled=document.getElementById('workflowEnabled').checked;var button=this;button.disabled=true;$.post(window.location.href,{definition:JSON.stringify(definition)},function(response){var data=typeof response==='string'?JSON.parse(response):response;if(data.result==='success'){window.location.reload();return;}button.disabled=false;zui.Modal.alert({message:typeof data.message==='string'?data.message:JSON.stringify(data.message)});}).fail(function(xhr){button.disabled=false;zui.Modal.alert({message:xhr.responseText||'Save failed'});});};
}
render();
})();
</script>

<?php
declare(strict_types=1);
/**
 * The manage view file of statetransition module.
 *
 * Visual workflow editor:
 *  - Mermaid flow diagram with clickable edges (select/deselect)
 *  - Node matrix: one column per source status, lists outgoing transitions
 *  - Transitions list: horizontal cards, click to select
 *  - Right panel: edit selected transition's properties
 *  - Bottom drawer: add new status / new transition
 *
 * Data flow:
 *  - PHP injects definition + dropdowns into JS via data-* attributes
 *  - JS maintains edit state, sends full definition back via POST on save
 */
namespace zin;

/* Inject data for JS. */
$definitionJSON = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$defaultDefJSON = json_encode($defaultDef, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$statusListJSON = json_encode($statusList);
$roleListJSON   = json_encode(array_values(array_filter($roleList, fn($v, $k) => $v !== '' && $k !== '', ARRAY_FILTER_USE_BOTH)));
$usersJSON      = json_encode($users);
$colorJSON      = json_encode($colorPresets);
$actionsJSON    = json_encode(array_combine($actions, array_map(fn($a) => $this->lang->statetransition->actionList[$a] ?? $a, $actions)));
$branchesJSON   = json_encode($this->lang->statetransition->branchList ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$actionBranchesJSON = json_encode($this->config->statetransition->actionBranches ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$systemStatusJSON = json_encode($systemStatuses);
$mermaidSource  = $this->statetransition->renderMermaid($definition);

/* Map status key → label for matrix display. */
$statusLabels = array();
foreach($statusList as $s) $statusLabels[$s['key']] = $s['text'];

/* Map status key → list of outgoing enabled transitions. */
$outgoingByStatus = array();
foreach($definition['transitions'] ?? array() as $tr)
{
    if(empty($tr['enabled'])) continue;
    if(!isset($outgoingByStatus[$tr['fromStatus']])) $outgoingByStatus[$tr['fromStatus']] = array();
    $outgoingByStatus[$tr['fromStatus']][] = $tr;
}
?>

<?php
/* Capture body content as raw HTML, then wrap in Zin html() node so the page wg
   picks it up as a child of #main (otherwise ZenTao renders an empty #main and
   pushes this content below the viewport). */
ob_start();
?>
<div id="workflowEditor"
     data-object-type="<?php echo htmlspecialchars($objectType); ?>"
     data-product-id="<?php echo (int)$productID; ?>"
     data-version="<?php echo (int)$version; ?>"
     data-is-default="<?php echo $isDefault ? '1' : '0'; ?>"
     data-enabled="<?php echo $enabled ? '1' : '0'; ?>"
     data-definition="<?php echo htmlspecialchars($definitionJSON, ENT_QUOTES); ?>"
     data-default-definition="<?php echo htmlspecialchars($defaultDefJSON, ENT_QUOTES); ?>"
     data-status-list="<?php echo htmlspecialchars($statusListJSON, ENT_QUOTES); ?>"
     data-role-list="<?php echo htmlspecialchars($roleListJSON, ENT_QUOTES); ?>"
     data-users="<?php echo htmlspecialchars($usersJSON, ENT_QUOTES); ?>"
     data-color-presets="<?php echo htmlspecialchars($colorJSON, ENT_QUOTES); ?>"
     data-actions="<?php echo htmlspecialchars($actionsJSON, ENT_QUOTES); ?>"
     data-branches="<?php echo htmlspecialchars($branchesJSON, ENT_QUOTES); ?>"
     data-action-branches="<?php echo htmlspecialchars($actionBranchesJSON, ENT_QUOTES); ?>"
     data-system-statuses="<?php echo htmlspecialchars($systemStatusJSON, ENT_QUOTES); ?>"
     data-save-url="<?php echo $this->createLink('statetransition', 'manage', "objectType={$objectType}&productID={$productID}"); ?>"
     data-reset-url="<?php echo $this->createLink('statetransition', 'reset', "objectType={$objectType}&productID={$productID}"); ?>"
     data-sync-global-url="<?php echo $this->createLink('statetransition', 'syncGlobal', "objectType={$objectType}&productID={$productID}"); ?>"
     data-browse-url="<?php echo $this->createLink('statetransition', 'browse', "objectType={$objectType}&productID={$productID}"); ?>"
     data-lang='{"enabledTip":"<?php echo htmlspecialchars($this->lang->statetransition->enabledTip); ?>","selectEdge":"<?php echo htmlspecialchars($this->lang->statetransition->selectEdge); ?>","allActors":"<?php echo htmlspecialchars($this->lang->statetransition->allActors); ?>","saveSuccess":"<?php echo htmlspecialchars($this->lang->saveSuccess); ?>","confirmReset":"<?php echo htmlspecialchars($this->lang->statetransition->confirmReset); ?>","confirmSyncFromGlobal":"<?php echo htmlspecialchars($this->lang->statetransition->confirmSyncFromGlobal); ?>","confirmDeleteEdge":"确定删除这条流转吗？","confirmDeleteNode":"确定删除此状态吗？关联的所有流转也会被删除。","entryNodeTitle":"<?php echo htmlspecialchars($this->lang->statetransition->entryNodeTitle); ?>","setAsEntry":"<?php echo htmlspecialchars($this->lang->statetransition->setAsEntry); ?>","unsetEntry":"<?php echo htmlspecialchars($this->lang->statetransition->unsetEntry); ?>","deleteNode":"<?php echo htmlspecialchars($this->lang->statetransition->deleteNode); ?>","nodeLabel":"<?php echo htmlspecialchars($this->lang->statetransition->nodeLabel); ?>","reviewBranch":"<?php echo htmlspecialchars($this->lang->statetransition->reviewBranch); ?>","reviewBranchTip":"<?php echo htmlspecialchars($this->lang->statetransition->reviewBranchTip); ?>","branchInvalid":"<?php echo htmlspecialchars($this->lang->statetransition->errors['branchInvalid']); ?>"}'
>

<!-- Header: feature tabs + actions -->
<header class="workflow-header">
    <nav class="workflow-tabs">
        <?php foreach($this->config->statetransition->objectTypes as $type):?>
        <a class="workflow-tab <?php if($type === $objectType) echo 'active';?>"
           href="<?php echo $this->createLink('statetransition', 'manage', "objectType={$type}&productID={$productID}"); ?>">
            <?php echo htmlspecialchars($this->lang->statetransition->objectTypeList[$type] ?? $type); ?>
        </a>
        <?php endforeach;?>
    </nav>
    <div class="workflow-actions">
        <label class="workflow-enabled-toggle" title="<?php echo htmlspecialchars($this->lang->statetransition->enabledTip); ?>">
            <input type="checkbox" id="workflowEnabled" <?php if($enabled) echo 'checked'; ?>>
            <span><?php echo htmlspecialchars($this->lang->statetransition->enabled); ?></span>
        </label>
        <button type="button" class="btn" id="resetWorkflow">
            <i class="icon icon-refresh"></i> <?php echo htmlspecialchars($this->lang->statetransition->reset); ?>
        </button>
        <?php if($productID > 0): ?>
        <button type="button" class="btn" id="syncGlobalWorkflow">
            <i class="icon icon-copy"></i> <?php echo htmlspecialchars($this->lang->statetransition->syncFromGlobal); ?>
        </button>
        <?php endif; ?>
        <button type="button" class="btn primary" id="saveWorkflow">
            <i class="icon icon-save"></i> <?php echo htmlspecialchars($this->lang->statetransition->saveWorkflow); ?>
        </button>
        <a class="btn" href="<?php echo $this->createLink('statetransition', 'browse', "objectType={$objectType}&productID={$productID}"); ?>">
            <i class="icon icon-back"></i> <?php echo htmlspecialchars($this->lang->statetransition->backToBrowse); ?>
        </a>
    </div>
</header>

<div class="workflow-body">
    <main class="workflow-main">
        <!-- 1. Mermaid diagram -->
        <section class="workflow-mermaid-panel">
            <div class="workflow-mermaid" id="workflowMermaid"><?php echo htmlspecialchars($mermaidSource); ?></div>
        </section>

        <!-- 2. Node matrix: each source status as a column -->
        <section class="workflow-section workflow-node-section">
            <header class="workflow-section-head"><?php echo htmlspecialchars($this->lang->statetransition->nodeMatrix); ?></header>
            <div class="workflow-board" id="workflowBoard">
                <?php foreach($definition['statuses'] ?? array() as $status):
                    $key = $status['key'];
                    $label = $statusLabels[$key] ?? ($status['label']['zh_cn'] ?? $key);
                    $isEntry = in_array($key, $definition['entries'] ?? array(), true);
                    $isSystem = !empty($status['isSystem']);
                    $outgoing = $outgoingByStatus[$key] ?? array();
                ?>
                <section class="workflow-column" data-status="<?php echo htmlspecialchars($key); ?>">
                    <div class="workflow-node <?php if($isEntry) echo 'is-entry'; ?>"
                         style="border-left-color: <?php echo htmlspecialchars($status['color'] ?? '#999'); ?>">
                        <span class="workflow-node-title">
                            <input type="text" class="workflow-node-label-input" data-node-label="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($label); ?>" aria-label="<?php echo htmlspecialchars($this->lang->statetransition->nodeLabel); ?>">
                            <span class="workflow-node-key"><?php echo htmlspecialchars($key); ?></span>
                        </span>
                        <button type="button" class="workflow-entry-toggle <?php if($isEntry) echo 'is-entry'; ?>"
                                data-toggle-entry="<?php echo htmlspecialchars($key); ?>"
                                title="<?php echo htmlspecialchars($this->lang->statetransition->entryNodeTitle); ?>">
                            <?php echo $isEntry ? $this->lang->statetransition->unsetEntry : $this->lang->statetransition->setAsEntry; ?>
                        </button>
                        <button type="button" class="workflow-node-delete" data-delete-node="<?php echo htmlspecialchars($key); ?>" title="<?php echo htmlspecialchars($this->lang->statetransition->deleteNode); ?>">×</button>
                    </div>
                    <?php if(empty($outgoing)): ?>
                        <div class="workflow-hint"><?php echo htmlspecialchars($this->lang->statetransition->flowEmpty); ?></div>
                    <?php else: foreach($outgoing as $edge):
                        $edgeLabel = $edge['label']['zh_cn'] ?? ($this->lang->statetransition->actionList[$edge['action']] ?? $edge['action']);
                    ?>
                        <button type="button" class="workflow-route" data-edge="<?php echo htmlspecialchars($edge['key']); ?>">
                            <span class="workflow-action-wrap">
                                <span class="workflow-action-label"><?php echo htmlspecialchars($edgeLabel); ?></span>
                                <span class="workflow-action-key"><?php echo htmlspecialchars($edge['action'] . (!empty($edge['branch']) ? '/' . $edge['branch'] : '')); ?></span>
                            </span>
                            <span class="workflow-arrow">→</span>
                            <span class="workflow-target"><?php echo htmlspecialchars($statusLabels[$edge['toStatus']] ?? $edge['toStatus']); ?></span>
                        </button>
                    <?php endforeach; endif; ?>
                </section>
                <?php endforeach;?>
            </div>
        </section>

        <!-- 3. Transitions list (all transitions as cards) -->
        <section class="workflow-section workflow-rule-section">
            <header class="workflow-section-head"><?php echo htmlspecialchars($this->lang->statetransition->ruleMatrix); ?></header>
            <div class="workflow-list" id="workflowList">
                <?php foreach($definition['transitions'] ?? array() as $edge):
                    $edgeLabel = $edge['label']['zh_cn'] ?? ($this->lang->statetransition->actionList[$edge['action']] ?? $edge['action']);
                    $disabled = empty($edge['enabled']);
                ?>
                <button type="button" class="workflow-transition <?php if($disabled) echo 'disabled'; ?> <?php if(!empty($edge['isCustom'])) echo 'is-custom'; ?>"
                        data-edge="<?php echo htmlspecialchars($edge['key']); ?>">
                    <span class="workflow-state"><?php echo htmlspecialchars($statusLabels[$edge['fromStatus']] ?? $edge['fromStatus']); ?></span>
                    <span class="workflow-arrow">→</span>
                    <span class="workflow-action-wrap">
                        <span class="workflow-action-label"><?php echo htmlspecialchars($edgeLabel); ?></span>
                        <span class="workflow-action-key"><?php echo htmlspecialchars($edge['action'] . (!empty($edge['branch']) ? '/' . $edge['branch'] : '')); ?></span>
                    </span>
                    <span class="workflow-arrow">→</span>
                    <span class="workflow-state"><?php echo htmlspecialchars($statusLabels[$edge['toStatus']] ?? $edge['toStatus']); ?></span>
                </button>
                <?php endforeach;?>
                <?php if(empty($definition['transitions'])): ?>
                <div class="workflow-rule-empty"><?php echo htmlspecialchars($this->lang->statetransition->flowEmpty); ?></div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Right panel: edit selected transition + add new -->
    <aside class="workflow-panel">
        <!-- Add new status -->
        <section class="workflow-add-section">
            <h3><?php echo htmlspecialchars($this->lang->statetransition->addNode); ?></h3>
            <div class="workflow-field">
                <input type="text" id="newNodeKey" placeholder="<?php echo htmlspecialchars($this->lang->statetransition->nodeID); ?>" pattern="[a-z][a-z0-9_]{1,29}">
            </div>
            <div class="workflow-field">
                <input type="text" id="newNodeLabel" placeholder="<?php echo htmlspecialchars($this->lang->statetransition->nodeLabel); ?>">
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->category); ?></label>
                <select id="newNodeCategory">
                    <?php foreach($this->lang->statetransition->categoryList as $k => $v): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->color); ?></label>
                <div class="workflow-color-row" id="newNodeColorRow">
                    <?php foreach($colorPresets as $i => $c): ?>
                    <label class="workflow-color-swatch <?php if($i === 0) echo 'selected'; ?>">
                        <input type="radio" name="newNodeColor" value="<?php echo htmlspecialchars($c); ?>" <?php if($i === 0) echo 'checked'; ?>>
                        <span style="background:<?php echo htmlspecialchars($c); ?>"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" class="btn btn-block btn-primary" id="addNode">
                <i class="icon icon-plus"></i> <?php echo htmlspecialchars($this->lang->statetransition->addNode); ?>
            </button>
        </section>

        <hr>

        <!-- Add new transition -->
        <section class="workflow-add-section">
            <h3><?php echo htmlspecialchars($this->lang->statetransition->addTransition); ?></h3>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->source); ?></label>
                <select id="newSource">
                    <?php foreach($statusList as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['key']); ?>"><?php echo htmlspecialchars($s['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->target); ?></label>
                <select id="newTarget">
                    <?php foreach($statusList as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['key']); ?>"><?php echo htmlspecialchars($s['text']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->action); ?></label>
                <select id="newAction">
                    <?php foreach($actions as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($this->lang->statetransition->actionList[$a] ?? $a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="workflow-field hidden" id="newBranchField">
                <label><?php echo htmlspecialchars($this->lang->statetransition->reviewBranch); ?></label>
                <select id="newBranch"></select>
                <div class="workflow-hint"><?php echo htmlspecialchars($this->lang->statetransition->reviewBranchTip); ?></div>
            </div>
            <div class="workflow-field">
                <input type="text" id="newTransitionLabel" placeholder="<?php echo htmlspecialchars($this->lang->statetransition->transitionName); ?>">
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->roles); ?></label>
                <div id="newRolesList" class="workflow-checkbox-group">
                <?php foreach($roleList as $rk => $rv): if($rk === '') continue; ?>
                    <label class="workflow-checkbox-item"><input type="checkbox" name="newRoles" value="<?php echo htmlspecialchars($rk); ?>"> <span><?php echo htmlspecialchars($rv); ?></span></label>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="workflow-field">
                <label><input type="checkbox" id="newRequireComment"> <?php echo htmlspecialchars($this->lang->statetransition->requireComment); ?></label>
            </div>
            <button type="button" class="btn btn-block btn-primary" id="addTransition">
                <i class="icon icon-plus"></i> <?php echo htmlspecialchars($this->lang->statetransition->addTransition); ?>
            </button>
        </section>

        <hr>

        <!-- Edit selected transition -->
        <section id="ruleEditor" class="hidden">
            <h3><?php echo htmlspecialchars($this->lang->statetransition->transitionRule); ?></h3>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->action); ?></label>
                <select id="edgeAction">
                    <?php foreach($actions as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($this->lang->statetransition->actionList[$a] ?? $a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="workflow-field hidden" id="edgeBranchField">
                <label><?php echo htmlspecialchars($this->lang->statetransition->reviewBranch); ?></label>
                <select id="edgeBranch"></select>
                <div class="workflow-hint"><?php echo htmlspecialchars($this->lang->statetransition->reviewBranchTip); ?></div>
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->transitionName); ?></label>
                <input type="text" id="edgeLabel">
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->roles); ?></label>
                <div id="edgeRolesList" class="workflow-checkbox-group">
                <?php foreach($roleList as $k => $v): if($k === '') continue; ?>
                    <label class="workflow-checkbox-item"><input type="checkbox" name="edgeRoles" value="<?php echo htmlspecialchars($k); ?>"> <span><?php echo htmlspecialchars($v); ?></span></label>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->accounts); ?></label>
                <div id="edgeAccountsList" class="workflow-checkbox-group">
                <?php foreach($users as $k => $v): ?>
                    <label class="workflow-checkbox-item"><input type="checkbox" name="edgeAccounts" value="<?php echo htmlspecialchars($k); ?>"> <span><?php echo htmlspecialchars($v); ?></span></label>
                <?php endforeach; ?>
                </div>
                <div class="workflow-hint"><?php echo htmlspecialchars($this->lang->statetransition->allActors); ?></div>
            </div>
            <div class="workflow-field">
                <label><input type="checkbox" id="edgeRequireComment"> <?php echo htmlspecialchars($this->lang->statetransition->requireComment); ?></label>
            </div>
            <div class="workflow-field">
                <label><input type="checkbox" id="edgeEnabled" checked> <?php echo htmlspecialchars($this->lang->statetransition->edgeEnabled); ?></label>
            </div>
            <div class="workflow-field">
                <label><input type="checkbox" id="edgeIsCustom"> <?php echo htmlspecialchars($this->lang->statetransition->customButton); ?></label>
            </div>
            <div class="workflow-field hidden" id="customButtonFields">
                <label><?php echo htmlspecialchars($this->lang->statetransition->buttonLabel); ?></label>
                <input type="text" id="edgeButtonLabel">
                <label class="mt-2"><?php echo htmlspecialchars($this->lang->statetransition->buttonIcon); ?></label>
                <input type="text" id="edgeButtonIcon" placeholder="ban / lock / flag">
            </div>
            <div class="workflow-field">
                <label><?php echo htmlspecialchars($this->lang->statetransition->description); ?></label>
                <textarea id="edgeDescription" rows="3"></textarea>
            </div>
            <button type="button" class="btn btn-block btn-danger" id="deleteTransition">
                <i class="icon icon-trash"></i> <?php echo htmlspecialchars($this->lang->statetransition->deleteEdge); ?>
            </button>
        </section>
        <div class="workflow-rule-empty" id="ruleEmpty"><?php echo htmlspecialchars($this->lang->statetransition->selectEdge); ?></div>
    </aside>
</div>

</div>
<?php
/* Inject captured body content as Zin node. */
$bodyContent = ob_get_clean();
html($bodyContent);
?>

<?php
/* Mermaid is bundled with ZenTao. CSS/JS for this method are auto-loaded from
   module/statetransition/css/manage.ui.css and js/manage.ui.js by ZenTao's view layer. */
$mermaidWebPath = $this->config->webRoot . 'js/zui3/mermaid/mermaid.min.js';
?>
<script src="<?php echo htmlspecialchars($mermaidWebPath); ?>"></script>

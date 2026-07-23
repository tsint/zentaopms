#!/usr/bin/env php
<?php
/**
title=测试详情页按钮严格遵循状态流转图;
timeout=0
cid=0

- 执行$requirementActions @submitReview,assignTo,edit
- 执行$epicActions @submitReview,assignTo,edit
- 执行$storyActions @submitReview,assignTo,edit
- 执行$bugActions @resolve,assignTo,edit
- 执行$taskActions @start,assignTo,edit
- 执行$requirementActivateAllowed @0
- 执行$epicActivateAllowed @0
- 执行$storyActivateAllowed @0
- 执行$bugActivateAllowed @0
- 执行$taskActivateAllowed @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester, $app, $config;
$tester->loadModel('statetransition');
$app->rawModule = 'story';

$productID   = 999201;
$executionID = 999202;
$config->URAndSR  = 1;
$config->enableER = 1;
$tester->app->loadConfig('epic');
$tester->app->loadConfig('requirement');
$tester->app->loadConfig('statetransition');
$config->statetransition->objectTypes = array_values(array_unique(array_merge($config->statetransition->objectTypes, array('epic', 'requirement'))));

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('product')->andWhere('productID')->eq($productID)->exec();
$tester->statetransition->clearCache();
dao::$errors = array();

$tester->dao->delete()->from(TABLE_PRODUCT)->where('id')->eq($productID)->exec();
$product = new stdclass();
$product->id          = $productID;
$product->name        = 'workflow detail action product';
$product->code        = 'workflow-detail-action';
$product->status      = 'normal';
$product->PO          = 'admin';
$product->acl         = 'open';
$product->createdBy   = 'admin';
$product->createdDate = helper::now();
$product->vision      = 'rnd';
$tester->dao->insert(TABLE_PRODUCT)->data($product)->exec();

$tester->dao->delete()->from(TABLE_PROJECT)->where('id')->eq($executionID)->exec();
$execution = new stdclass();
$execution->id          = $executionID;
$execution->project     = 0;
$execution->model       = 'scrum';
$execution->type        = 'sprint';
$execution->name        = 'workflow detail action execution';
$execution->code        = 'workflow-detail-action-execution';
$execution->status      = 'wait';
$execution->openedBy    = 'admin';
$execution->openedDate  = helper::now();
$execution->PM          = 'admin';
$execution->acl         = 'open';
$execution->vision      = 'rnd';
$execution->multiple    = 1;
$execution->hasProduct  = 1;
$execution->deleted     = 0;
$tester->dao->insert(TABLE_PROJECT)->data($execution)->exec();

$tester->dao->delete()->from(TABLE_PROJECTPRODUCT)->where('project')->eq($executionID)->exec();
$projectProduct = new stdclass();
$projectProduct->project = $executionID;
$projectProduct->product = $productID;
$projectProduct->branch  = 0;
$projectProduct->plan    = 0;
$tester->dao->insert(TABLE_PROJECTPRODUCT)->data($projectProduct)->exec();

$makeStoryDefinition = function() {
    $status = function(string $key, string $label, string $category = 'normal', bool $entry = false) {
        return array('key' => $key, 'label' => array('zh_cn' => $label, 'en' => $label), 'category' => $category, 'color' => '#1abc9c', 'isSystem' => false, 'isEntry' => $entry, 'fieldRules' => new stdClass());
    };
    $transition = function(string $from, string $to, string $action, string $label, ?string $branch = null) {
        return array('key' => "$from-to-$to-via-$action" . ($branch ? "-$branch" : ''), 'fromStatus' => $from, 'toStatus' => $to, 'action' => $action, 'branch' => $branch, 'label' => array('zh_cn' => $label, 'en' => $label), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null);
    };

    return array(
        'schemaVersion' => 1,
        'statuses' => array(
            $status('collecting', '收集需求', 'normal', true),
            $status('confirming', '确认需求'),
            $status('reviewing', '评审中'),
            $status('active', '激活'),
            $status('closed', '已关闭', 'terminal'),
        ),
        'transitions' => array(
            $transition('collecting', 'confirming', 'activate', '激活'),
            $transition('confirming', 'reviewing', 'submitreview', '提交评审'),
            $transition('reviewing', 'active', 'review', '评审通过', 'pass'),
            $transition('reviewing', 'closed', 'review', '拒绝', 'reject'),
            $transition('active', 'closed', 'close', '关闭'),
            $transition('closed', 'active', 'activate', '激活'),
        ),
        'entries' => array('collecting'),
    );
};

foreach(array('epic', 'requirement', 'story') as $objectType) $tester->statetransition->saveDefinition($objectType, 0, $makeStoryDefinition(), 0, true);

$bugDef = $tester->statetransition->getDefaultDefinition('bug');
$bugDef['statuses'][] = array('key' => 'triage', 'label' => array('zh_cn' => '待分拣', 'en' => 'Triage'), 'category' => 'normal', 'color' => '#1abc9c', 'isSystem' => false, 'isEntry' => true, 'fieldRules' => new stdClass());
$bugDef['entries'] = array('triage');
$bugDef['transitions'] = array_values(array_filter($bugDef['transitions'], function($tr) { return $tr['fromStatus'] === 'active' && $tr['action'] === 'resolve'; }));
$bugDef['transitions'][] = array('key' => 'triage-to-resolved-via-resolve', 'fromStatus' => 'triage', 'toStatus' => 'resolved', 'action' => 'resolve', 'branch' => null, 'label' => array('zh_cn' => '解决', 'en' => 'Resolve'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null);
$tester->statetransition->saveDefinition('bug', 0, $bugDef, 0, true);

$taskDef = $tester->statetransition->getDefaultDefinition('task');
$taskDef['statuses'][] = array('key' => 'queued', 'label' => array('zh_cn' => '待排期', 'en' => 'Queued'), 'category' => 'normal', 'color' => '#1abc9c', 'isSystem' => false, 'isEntry' => true, 'fieldRules' => new stdClass());
$taskDef['entries'] = array('queued');
$taskDef['transitions'] = array_values(array_filter($taskDef['transitions'], function($tr) { return $tr['fromStatus'] === 'wait' && $tr['action'] === 'start'; }));
$taskDef['transitions'][] = array('key' => 'queued-to-doing-via-start', 'fromStatus' => 'queued', 'toStatus' => 'doing', 'action' => 'start', 'branch' => null, 'label' => array('zh_cn' => '开始', 'en' => 'Start'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null);
$tester->statetransition->saveDefinition('task', 0, $taskDef, 0, true);
$tester->statetransition->clearCache();

$storyIDs = array();
foreach(array('epic', 'requirement', 'story') as $type)
{
    $story = new stdclass();
    $story->product     = $productID;
    $story->branch      = 0;
    $story->module      = 0;
    $story->type        = $type;
    $story->title       = "detail action $type";
    $story->status      = 'confirming';
    $story->stage       = 'wait';
    $story->openedBy    = 'admin';
    $story->openedDate  = helper::now();
    $story->assignedTo  = '';
    $story->version     = 1;
    $story->pri         = 3;
    $story->grade       = 1;
    $story->deleted     = 0;
    $tester->dao->insert(TABLE_STORY)->data($story)->exec();
    $storyID = (int)$tester->dao->lastInsertID();
    $tester->dao->update(TABLE_STORY)->set('root')->eq($storyID)->set('path')->eq(",{$storyID},")->where('id')->eq($storyID)->exec();

    $spec = new stdclass();
    $spec->story   = $storyID;
    $spec->version = 1;
    $spec->title   = $story->title;
    $spec->spec    = '';
    $spec->verify  = '';
    $tester->dao->insert(TABLE_STORYSPEC)->data($spec)->exec();
    $storyIDs[$type] = $storyID;
}

$bug = new stdclass();
$bug->product     = $productID;
$bug->execution   = $executionID;
$bug->project     = 0;
$bug->title       = 'detail action bug';
$bug->status      = 'triage';
$bug->type        = 'codeerror';
$bug->severity    = 3;
$bug->pri         = 3;
$bug->openedBy    = 'admin';
$bug->openedDate  = helper::now();
$bug->openedBuild = 'trunk';
$bug->assignedTo  = '';
$bug->confirmed   = 0;
$bug->deleted     = 0;
$tester->dao->insert(TABLE_BUG)->data($bug)->exec();
$bugID = (int)$tester->dao->lastInsertID();

$task = new stdclass();
$task->project     = 0;
$task->execution   = $executionID;
$task->module      = 0;
$task->story       = 0;
$task->storyVersion = 0;
$task->name        = 'detail action task';
$task->type        = 'devel';
$task->status      = 'queued';
$task->pri         = 3;
$task->estimate    = 0;
$task->consumed    = 0;
$task->left        = 0;
$task->openedBy    = 'admin';
$task->openedDate  = helper::now();
$task->assignedTo  = '';
$task->version     = 1;
$task->deleted     = 0;
$tester->dao->insert(TABLE_TASK)->data($task)->exec();
$taskID = (int)$tester->dao->lastInsertID();
$tester->dao->update(TABLE_TASK)->set('path')->eq(",{$taskID},")->where('id')->eq($taskID)->exec();

$storyActions = array(array('name' => 'submitReview'), array('name' => 'activate'), array('name' => 'close'), array('name' => 'assignTo'), array('name' => 'edit'));
$bugActions   = array(array('name' => 'resolve'), array('name' => 'activate'), array('name' => 'close'), array('name' => 'assignTo'), array('name' => 'edit'));
$taskActions  = array(array('name' => 'start'), array('name' => 'activate'), array('name' => 'close'), array('name' => 'assignTo'), array('name' => 'edit'));

$actionNames = function(array $actions): string { return implode(',', array_map(fn($action) => $action['name'] ?? '', $actions)); };
$requirementActions = $actionNames($tester->statetransition->filterDetailActions('requirement', 0, 'confirming', $storyActions));
$epicActions        = $actionNames($tester->statetransition->filterDetailActions('epic', 0, 'confirming', $storyActions));
$storyActionsResult = $actionNames($tester->statetransition->filterDetailActions('story', 0, 'confirming', $storyActions));
$bugActionsResult   = $actionNames($tester->statetransition->filterDetailActions('bug', 0, 'triage', $bugActions));
$taskActionsResult  = $actionNames($tester->statetransition->filterDetailActions('task', 0, 'queued', $taskActions));

$requirementActivateAllowed = $tester->statetransition->isActionAllowed('requirement', 0, 'confirming', 'activate') ? '1' : '0';
$epicActivateAllowed        = $tester->statetransition->isActionAllowed('epic', 0, 'confirming', 'activate') ? '1' : '0';
$storyActivateAllowed       = $tester->statetransition->isActionAllowed('story', 0, 'confirming', 'activate') ? '1' : '0';
$bugActivateAllowed         = $tester->statetransition->isActionAllowed('bug', 0, 'triage', 'activate') ? '1' : '0';
$taskActivateAllowed        = $tester->statetransition->isActionAllowed('task', 0, 'queued', 'activate') ? '1' : '0';

r($requirementActions) && p() && e('submitReview,assignTo,edit');
r($epicActions) && p() && e('submitReview,assignTo,edit');
r($storyActionsResult) && p() && e('submitReview,assignTo,edit');
r($bugActionsResult) && p() && e('resolve,assignTo,edit');
r($taskActionsResult) && p() && e('start,assignTo,edit');
r($requirementActivateAllowed) && p() && e('0');
r($epicActivateAllowed) && p() && e('0');
r($storyActivateAllowed) && p() && e('0');
r($bugActivateAllowed) && p() && e('0');
r($taskActivateAllowed) && p() && e('0');

echo "IDS {$storyIDs['epic']} {$storyIDs['requirement']} {$storyIDs['story']} $bugID $taskID\n";

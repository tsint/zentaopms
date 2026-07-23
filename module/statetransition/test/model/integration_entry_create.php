#!/usr/bin/env php
<?php
/**
title=测试创建对象时应用自定义初始状态;
timeout=0
cid=0

- 执行$epicStatus @collecting
- 执行$requirementStatus @collecting
- 执行$storyStatus @collecting
- 执行$draftRequirementStatus @collecting
- 执行$bugStatus @triage
- 执行$taskStatus @queued
- 执行$unclosedHasRequirement @1
- 执行$unclosedHasDraftRequirement @1
- 执行$requirementStatusLabel @收集需求
- 执行$bugStatusLabel @待分拣
- 执行$taskStatusLabel @待排期
- 执行$formattedRequirementStatus @collecting
- 执行$formattedRequirementStatusLabel @收集需求
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester, $app, $config;
$tester->loadModel('statetransition');
$tester->loadModel('story');
$tester->loadModel('bug');
$tester->loadModel('task');
$tester->loadModel('product');
$app->rawModule = 'story';

$productID   = 999101;
$executionID = 999102;
$config->URAndSR  = 1;
$config->enableER = 1;
$tester->app->loadConfig('epic');
$tester->app->loadConfig('requirement');
$tester->app->loadConfig('statetransition');
$config->statetransition->objectTypes = array_values(array_unique(array_merge($config->statetransition->objectTypes, array('epic', 'requirement'))));

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
dao::$errors = array();

$tester->dao->delete()->from(TABLE_PRODUCT)->where('id')->eq($productID)->exec();
$product = new stdclass();
$product->id          = $productID;
$product->name        = 'workflow entry create product';
$product->code        = 'workflow-entry-create';
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
$execution->name        = 'workflow entry create execution';
$execution->code        = 'workflow-entry-execution';
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

$makeEntryDefinition = function(string $objectType, string $entryKey, string $entryLabel) use ($tester) {
    $definition = $tester->statetransition->getDefaultDefinition($objectType);
    $definition['statuses'] = array_values(array_filter($definition['statuses'], function($status) {
        return !in_array($status['key'], array('draft', 'active', 'wait'), true);
    }));
    $definition['statuses'][] = array(
        'key'        => $entryKey,
        'label'      => array('zh_cn' => $entryLabel, 'en' => $entryLabel),
        'category'   => 'normal',
        'color'      => '#1abc9c',
        'isSystem'   => false,
        'isEntry'    => true,
        'fieldRules' => new stdClass(),
    );
    $definition['entries'] = array($entryKey);
    $definition['transitions'] = array_values(array_filter($definition['transitions'], function($transition) {
        return !in_array($transition['fromStatus'], array('draft', 'active', 'wait'), true) && !in_array($transition['toStatus'], array('draft', 'active', 'wait'), true);
    }));
    return $definition;
};

foreach(array('epic', 'requirement', 'story') as $storyType) $tester->statetransition->saveDefinition($storyType, 0, $makeEntryDefinition($storyType, 'collecting', '收集需求'), 0, true);
$tester->statetransition->saveDefinition('bug', 0, $makeEntryDefinition('bug', 'triage', '待分拣'), 0, true);
$tester->statetransition->saveDefinition('task', 0, $makeEntryDefinition('task', 'queued', '待排期'), 0, true);

$createStory = function(string $type, string $status, string $title) use ($tester, $productID) {
    $story = new stdclass();
    $story->product     = $productID;
    $story->branch      = 0;
    $story->type        = $type;
    $story->title       = $title;
    $story->status      = $status;
    $story->stage       = 'wait';
    $story->openedBy    = 'admin';
    $story->openedDate  = helper::now();
    $story->assignedTo  = '';
    $story->version     = 1;
    $story->spec        = '';
    $story->verify      = '';
    $_POST['uid'] = '';
    return $tester->story->create($story);
};

$epicID             = $createStory('epic', 'active', 'entry create epic');
$requirementID      = $createStory('requirement', 'active', 'entry create requirement');
$storyID            = $createStory('story', 'active', 'entry create story');
$draftRequirementID = $createStory('requirement', 'draft', 'entry create draft requirement');

$bug = new stdclass();
$bug->product     = $productID;
$bug->execution   = $executionID;
$bug->title       = 'entry create bug';
$bug->type        = 'codeerror';
$bug->openedBuild = 'trunk';
$bug->pri         = 3;
$bug->severity    = 3;
$bug->status      = 'active';
$bug->deadline    = '2026-07-23';
$bug->openedBy    = 'admin';
$bug->openedDate  = helper::now();
$bug->notifyEmail = '';
$bug->steps       = '';
$bugID = $tester->bug->create($bug);

$task = new stdclass();
$task->project     = 0;
$task->execution   = $executionID;
$task->module      = 0;
$task->story       = 0;
$task->name        = 'entry create task';
$task->type        = 'devel';
$task->mode        = '';
$task->status      = 'wait';
$task->assignedTo  = '';
$task->pri         = 3;
$task->estimate    = 0;
$task->left        = 0;
$task->estStarted  = '2026-07-23';
$task->deadline    = '2026-07-23';
$task->desc        = '';
$task->version     = 1;
$task->openedBy    = 'admin';
$task->openedDate  = helper::now();
$_POST['uid'] = '';
$taskID = $tester->task->create($task);

$epicStatus             = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($epicID)->fetch('status');
$requirementStatus      = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($requirementID)->fetch('status');
$storyStatus            = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch('status');
$draftRequirementStatus = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($draftRequirementID)->fetch('status');
$bugStatus              = $tester->dao->select('status')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch('status');
$taskStatus             = $tester->dao->select('status')->from(TABLE_TASK)->where('id')->eq($taskID)->fetch('status');
$tester->app->loadClass('pager', true);
$app->rawModule = 'product';
$app->rawMethod = 'browse';
$pager                  = new pager(0, 20, 1);
$unclosedStories        = $tester->product->getStories($productID, 'all', 'unclosed', 0, 0, 'requirement', 'id_desc', $pager);
$unclosedHasRequirement = isset($unclosedStories[$requirementID]) ? 1 : 0;
$unclosedHasDraftRequirement = isset($unclosedStories[$draftRequirementID]) ? 1 : 0;
$requirementStatusLabel = $tester->story->processStatus('story', $tester->story->getByID($requirementID));
$bugStatusLabel         = $tester->bug->processStatus('bug', $tester->bug->getByID($bugID));
$taskStatusLabel        = $tester->task->processStatus('task', $tester->task->getByID($taskID));
$formattedRequirement   = $tester->story->formatStoryForList($tester->story->getByID($requirementID), array('users' => array()), 'requirement', $tester->story->getMaxGradeGroup());
$formattedRequirementStatus = $formattedRequirement->status;
$formattedRequirementStatusLabel = $formattedRequirement->statusLabel;

r($epicStatus) && p() && e('collecting');
r($requirementStatus) && p() && e('collecting');
r($storyStatus) && p() && e('collecting');
r($draftRequirementStatus) && p() && e('collecting');
r($bugStatus) && p() && e('triage');
r($taskStatus) && p() && e('queued');
r($unclosedHasRequirement) && p() && e('1');
r($unclosedHasDraftRequirement) && p() && e('1');
r($requirementStatusLabel) && p() && e('收集需求');
r($bugStatusLabel) && p() && e('待分拣');
r($taskStatusLabel) && p() && e('待排期');
r($formattedRequirementStatus) && p() && e('collecting');
r($formattedRequirementStatusLabel) && p() && e('收集需求');

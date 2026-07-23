#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->filterDetailActions();
timeout=0
cid=0

- 执行$storyFiltered @submitReview,assignTo,subdivide,edit,createTask

- 执行$requirementFiltered @submitReview,assignTo,subdivide,edit,createTask

- 执行$epicFiltered @submitReview,assignTo,subdivide,edit,createTask

- 执行$bugFiltered @resolve,assignTo,edit

- 执行$taskFiltered @start,assignTo,edit,recordWorkhour

- 执行$storyActivateAllowed @0

- 执行$storyActivateDecision->ok @0

- 执行$bugActivateAllowed @0

- 执行$taskActivateAllowed @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester, $config;
$tester->loadModel('statetransition');
$config->URAndSR  = 1;
$config->enableER = 1;
$tester->app->loadConfig('statetransition');
$config->statetransition->objectTypes = array_values(array_unique(array_merge($config->statetransition->objectTypes, array('epic', 'requirement'))));

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

$storyActions = array(
    array('name' => 'submitReview'),
    array('name' => 'close'),
    array('name' => 'activate'),
    array('name' => 'assignTo'),
    array('name' => 'subdivide'),
    array('name' => 'edit'),
    array('name' => 'createTask')
);

$bugActions = array(
    array('name' => 'resolve'),
    array('name' => 'close'),
    array('name' => 'activate'),
    array('name' => 'confirm'),
    array('name' => 'assignTo'),
    array('name' => 'edit')
);

$taskActions = array(
    array('name' => 'start'),
    array('name' => 'close'),
    array('name' => 'activate'),
    array('name' => 'assignTo'),
    array('name' => 'edit'),
    array('name' => 'recordWorkhour')
);

function actionNames(array $actions): string
{
    return implode(',', array_map(fn($action) => $action['name'] ?? '', $actions));
}

function minimalStoryDefinition(): array
{
    return array(
        'schemaVersion' => 1,
        'statuses' => array(
            array('key' => 'draft', 'label' => array('zh_cn' => '草稿', 'en' => 'Draft'), 'category' => 'normal', 'color' => '#999999', 'isSystem' => true, 'isEntry' => true, 'fieldRules' => new stdClass()),
            array('key' => 'reviewing', 'label' => array('zh_cn' => '评审中', 'en' => 'Reviewing'), 'category' => 'normal', 'color' => '#3498db', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
            array('key' => 'active', 'label' => array('zh_cn' => '激活', 'en' => 'Active'), 'category' => 'normal', 'color' => '#27ae60', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        ),
        'transitions' => array(
            array('key' => 'draft-to-reviewing-via-submitreview', 'fromStatus' => 'draft', 'toStatus' => 'reviewing', 'action' => 'submitreview', 'branch' => null, 'label' => array('zh_cn' => '提交评审', 'en' => 'Submit review'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
            array('key' => 'reviewing-to-active-via-activate', 'fromStatus' => 'reviewing', 'toStatus' => 'active', 'action' => 'activate', 'branch' => null, 'label' => array('zh_cn' => '激活', 'en' => 'Activate'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        ),
        'entries' => array('draft'),
    );
}

$storyDef = minimalStoryDefinition();
foreach(array('story', 'requirement', 'epic') as $objectType)
{
    if(!in_array($objectType, $config->statetransition->objectTypes)) continue;
    $tester->statetransition->saveDefinition($objectType, 0, $storyDef, 0, true);
}

$bugDef = $tester->statetransition->getDefaultDefinition('bug');
$bugDef['transitions'] = array_values(array_filter($bugDef['transitions'], fn($tr) => $tr['fromStatus'] === 'active' && $tr['action'] === 'resolve'));
$tester->statetransition->saveDefinition('bug', 0, $bugDef, 0, true);

$taskDef = $tester->statetransition->getDefaultDefinition('task');
$taskDef['transitions'] = array_values(array_filter($taskDef['transitions'], fn($tr) => $tr['fromStatus'] === 'wait' && $tr['action'] === 'start'));
$tester->statetransition->saveDefinition('task', 0, $taskDef, 0, true);
$tester->statetransition->clearCache();

$storyFiltered = $tester->statetransition->filterDetailActions('story', 0, 'draft', $storyActions);
$requirementFiltered = in_array('requirement', $config->statetransition->objectTypes) ? $tester->statetransition->filterDetailActions('requirement', 0, 'draft', $storyActions) : $storyFiltered;
$epicFiltered = in_array('epic', $config->statetransition->objectTypes) ? $tester->statetransition->filterDetailActions('epic', 0, 'draft', $storyActions) : $storyFiltered;
$bugFiltered = $tester->statetransition->filterDetailActions('bug', 0, 'active', $bugActions);
$taskFiltered = $tester->statetransition->filterDetailActions('task', 0, 'wait', $taskActions);
$storyActivateAllowed = $tester->statetransition->isActionAllowed('story', 0, 'draft', 'activate') ? '1' : '0';
$storyActivateDecision = $tester->statetransition->transition('story', 0, 0, 'draft', 'activate');
$bugActivateAllowed = $tester->statetransition->isActionAllowed('bug', 0, 'active', 'activate') ? '1' : '0';
$taskActivateAllowed = $tester->statetransition->isActionAllowed('task', 0, 'wait', 'activate') ? '1' : '0';

r(actionNames($storyFiltered)) && p() && e('submitReview,assignTo,subdivide,edit,createTask');
r(actionNames($requirementFiltered)) && p() && e('submitReview,assignTo,subdivide,edit,createTask');
r(actionNames($epicFiltered)) && p() && e('submitReview,assignTo,subdivide,edit,createTask');
r(actionNames($bugFiltered)) && p() && e('resolve,assignTo,edit');
r(actionNames($taskFiltered)) && p() && e('start,assignTo,edit,recordWorkhour');
r($storyActivateAllowed) && p() && e('0');
r($storyActivateDecision) && p('ok') && e('0');
r($bugActivateAllowed) && p() && e('0');
r($taskActivateAllowed) && p() && e('0');

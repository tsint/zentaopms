#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->getActionLabelOverrides();
timeout=0
cid=0

- 执行$noDefOverrides @0
- 执行$noCustomOverrides @0
- 执行$withCustomOverrides['activate'] @测试
- 执行$withCustomOverrides @1
- 执行$branchSkipped @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

/* Only clear global scope to avoid wiping user's product-scope definitions. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → empty result. */
$noDefOverrides = $tester->statetransition->getActionLabelOverrides('story', 0, 'active');

/* Case 2: default definition → no custom labels (all use default action labels). */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);
$noCustomOverrides = $tester->statetransition->getActionLabelOverrides('story', 0, 'active');

/* Case 3: customize the activate transition's buttonLabel from active status. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$storyDef['statuses'][] = array(
    'key' => 'testing',
    'label' => array('zh_cn' => '测试中', 'en' => 'Testing'),
    'category' => 'normal',
    'color' => '#f39c12',
    'isSystem' => false,
    'isEntry' => false,
    'fieldRules' => new stdClass(),
);
$storyDef['transitions'][] = array(
    'key' => 'active-to-testing-via-activate',
    'fromStatus' => 'active',
    'toStatus' => 'testing',
    'action' => 'activate',
    'branch' => null,
    'label' => array('zh_cn' => '测试', 'en' => 'Test'),
    'roles' => array(),
    'accounts' => array(),
    'requireComment' => false,
    'enabled' => true,
    'isCustom' => false,
    'buttonLabel' => array('zh_cn' => '测试', 'en' => 'Test'),
    'buttonIcon' => null,
    'buttonOrder' => 0,
    'buttonGroup' => 'primary',
    'sideEffects' => array(),
    'condition' => null,
);
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);
$tester->statetransition->clearCache();

$withCustomOverrides = $tester->statetransition->getActionLabelOverrides('story', 0, 'active');

/* Case 5: when multiple branches exist for same action (review pass/reject/clarify from reviewing),
   the override is skipped because the native button opens a form to pick a branch. */
$branchSkipped = $tester->statetransition->getActionLabelOverrides('story', 0, 'reviewing');

r(count($noDefOverrides)) && p() && e('0');
r(count($noCustomOverrides)) && p() && e('0');
r($withCustomOverrides['activate']) && p() && e('测试');
r(count($withCustomOverrides)) && p() && e('1');
r(count($branchSkipped)) && p() && e('0');
#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->getCustomButtons();
timeout=0
cid=0

- 执行$defaultButtons @0
- 执行$customButtons @1
- 执行$customButtons[0]['buttonLabel'] @标记阻塞
- 执行$customButtons[0]['action'] @custom_mark_blocked
- 执行$customButtons[0]['toStatus'] @custom_blocked
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: default definition has no custom buttons. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$defaultButtons = $tester->statetransition->getCustomButtons('story', 0, 'active');

/* Case 2-5: with a custom button. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$storyDef['statuses'][] = array(
    'key' => 'custom_blocked',
    'label' => array('zh_cn' => '已阻塞', 'en' => 'Blocked'),
    'category' => 'abnormal',
    'color' => '#e74c3c',
    'isSystem' => false,
    'isEntry' => false,
    'fieldRules' => new stdClass(),
);
$storyDef['transitions'][] = array(
    'key' => 'active-to-blocked-via-custom_mark_blocked',
    'fromStatus' => 'active',
    'toStatus' => 'custom_blocked',
    'action' => 'custom_mark_blocked',
    'branch' => null,
    'label' => array('zh_cn' => '标记阻塞', 'en' => 'Mark Blocked'),
    'roles' => array(),
    'accounts' => array(),
    'requireComment' => true,
    'enabled' => true,
    'isCustom' => true,
    'buttonLabel' => array('zh_cn' => '标记阻塞', 'en' => 'Mark Blocked'),
    'buttonIcon' => 'ban-circle',
    'buttonOrder' => 1,
    'buttonGroup' => 'danger',
    'sideEffects' => array(),
    'condition' => null,
);
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$customButtons = $tester->statetransition->getCustomButtons('story', 0, 'active');

r(count($defaultButtons)) && p() && e('0');
r(count($customButtons)) && p() && e('1');
r($customButtons[0]['buttonLabel']) && p() && e('标记阻塞');
r($customButtons[0]['action']) && p() && e('custom_mark_blocked');
r($customButtons[0]['toStatus']) && p() && e('custom_blocked');
#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->validateDefinition();
timeout=0
cid=0

- 执行$validResult['ok'] @1
- 执行$validResult['errors'] @0
- 执行$dupResult['ok'] @0
- 执行$badKeyResult['ok'] @0
- 执行$badRefResult['ok'] @0
- 执行$duplicateResult['ok'] @0
- 执行$invalidTypeResult['ok'] @0
- 执行$systemCollisionResult['ok'] @0
- 执行$deleteSystemResult['ok'] @1
- 执行$reviewBranchResult['ok'] @1
- 执行$invalidBranchResult['ok'] @0
- 执行$renamedStatusLabel @确认需求
- 执行$restoreSystemResult['ok'] @1
- 执行$restoredActiveIsSystem @1
- 执行$reviewWrongSourceResult['ok'] @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$validStory = $tester->statetransition->getDefaultDefinition('story');
$validResult = $tester->statetransition->validateDefinition($validStory, 'story');

$dupStatus = $tester->statetransition->getDefaultDefinition('story');
$dupStatus['statuses'][1]['key'] = 'draft';
$dupResult = $tester->statetransition->validateDefinition($dupStatus, 'story');

$badKey = $tester->statetransition->getDefaultDefinition('story');
$badKey['statuses'][0]['key'] = 'BadKey';
$badKeyResult = $tester->statetransition->validateDefinition($badKey, 'story');

$badRef = $tester->statetransition->getDefaultDefinition('story');
$badRef['transitions'][0]['toStatus'] = 'nonexistent_status';
$badRefResult = $tester->statetransition->validateDefinition($badRef, 'story');

$duplicateTransition = $tester->statetransition->getDefaultDefinition('story');
$duplicateTransition['transitions'][] = $duplicateTransition['transitions'][0];
$duplicateTransition['transitions'][count($duplicateTransition['transitions'])-1]['key'] = 'duplicate-test';
$duplicateResult = $tester->statetransition->validateDefinition($duplicateTransition, 'story');

$invalidType = $tester->statetransition->getDefaultDefinition('story');
$invalidTypeResult = $tester->statetransition->validateDefinition($invalidType, 'nonexistent');

$systemCollision = $tester->statetransition->getDefaultDefinition('story');
$systemCollision['statuses'][0]['isSystem'] = false;
$systemCollisionResult = $tester->statetransition->validateDefinition($systemCollision, 'story');

$deleteSystem = $tester->statetransition->getDefaultDefinition('story');
$deleteSystem['statuses'] = array_values(array_filter($deleteSystem['statuses'], function($status) { return !in_array($status['key'], array('draft', 'active'), true); }));
$deleteSystem['entries'] = array('reviewing');
$deleteSystem['transitions'] = array_values(array_filter($deleteSystem['transitions'], function($transition) {
    return !in_array($transition['fromStatus'], array('draft', 'active'), true) && !in_array($transition['toStatus'], array('draft', 'active'), true);
}));
$deleteSystemResult = $tester->statetransition->validateDefinition($deleteSystem, 'story');

$reviewBranch = $tester->statetransition->getDefaultDefinition('story');
$reviewBranch['statuses'][1]['label'] = array('zh_cn' => '确认需求', 'en' => 'Confirming');
$reviewBranch['transitions'][] = $reviewBranch['transitions'][1];
$reviewBranch['transitions'][count($reviewBranch['transitions'])-1]['key']      = 'reviewing-to-draft-via-review-revert';
$reviewBranch['transitions'][count($reviewBranch['transitions'])-1]['toStatus'] = 'draft';
$reviewBranch['transitions'][count($reviewBranch['transitions'])-1]['branch']   = 'revert';
$reviewBranch['transitions'][count($reviewBranch['transitions'])-1]['label']    = array('zh_cn' => '撤回评审', 'en' => 'Revert');
$reviewBranchResult = $tester->statetransition->validateDefinition($reviewBranch, 'story');
$renamedStatusLabel = $reviewBranchResult['definition']['statuses'][1]['label']['zh_cn'] ?? '';

$invalidBranch = $tester->statetransition->getDefaultDefinition('story');
$invalidBranch['transitions'][1]['branch'] = 'whatever';
$invalidBranchResult = $tester->statetransition->validateDefinition($invalidBranch, 'story');

$restoreSystem = $deleteSystem;
$restoreSystem['statuses'][] = array('key' => 'active', 'label' => array('zh_cn' => '激活', 'en' => 'Active'), 'category' => 'normal', 'color' => '#27ae60', 'isSystem' => true, 'isEntry' => true, 'fieldRules' => new stdClass());
$restoreSystem['entries'][] = 'active';
$restoreSystemResult = $tester->statetransition->validateDefinition($restoreSystem, 'story');
$restoredActive = null;
foreach($restoreSystemResult['definition']['statuses'] ?? array() as $status)
{
    if($status['key'] === 'active') $restoredActive = $status;
}
$restoredActiveIsSystem = !empty($restoredActive['isSystem']) ? '1' : '0';

$reviewWrongSource = $tester->statetransition->getDefaultDefinition('story');
$reviewWrongSource['transitions'][1]['fromStatus'] = 'draft';
$reviewWrongSource['transitions'][1]['key'] = 'draft-to-active-via-review-pass';
$reviewWrongSourceResult = $tester->statetransition->validateDefinition($reviewWrongSource, 'story');

r($validResult['ok']) && p() && e('1');
r(count($validResult['errors'])) && p() && e('0');
r($dupResult['ok']) && p() && e('0');
r($badKeyResult['ok']) && p() && e('0');
r($badRefResult['ok']) && p() && e('0');
r($duplicateResult['ok']) && p() && e('0');
r($invalidTypeResult['ok']) && p() && e('0');
r($systemCollisionResult['ok']) && p() && e('0');
r($deleteSystemResult['ok']) && p() && e('1');
r($reviewBranchResult['ok']) && p() && e('1');
r($invalidBranchResult['ok']) && p() && e('0');
r($renamedStatusLabel) && p() && e('确认需求');
r($restoreSystemResult['ok']) && p() && e('1');
r($restoredActiveIsSystem) && p() && e('1');
r($reviewWrongSourceResult['ok']) && p() && e('0');

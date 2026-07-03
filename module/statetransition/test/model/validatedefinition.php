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

r($validResult['ok']) && p() && e('1');
r(count($validResult['errors'])) && p() && e('0');
r($dupResult['ok']) && p() && e('0');
r($badKeyResult['ok']) && p() && e('0');
r($badRefResult['ok']) && p() && e('0');
r($duplicateResult['ok']) && p() && e('0');
r($invalidTypeResult['ok']) && p() && e('0');
r($systemCollisionResult['ok']) && p() && e('0');
#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->saveDefinition() & getDefinition();
timeout=0
cid=0

- 执行$firstSave['ok'] @1
- 执行$firstSave['version'] @1
- 执行$fetched['enabled'] ? '1' : '0 @1
- 执行$fetched['objectType'] @story
- 执行$secondSave['version'] @2
- 执行$conflict['error'] @versionConflict
- 执行$disabledFetched['enabled'] ? '1' : '0 @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

$storyDef = $tester->statetransition->getDefaultDefinition('story');

/* Case 1-2: first save → version=1. */
$firstSave = $tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

/* Case 3-4: get it back. */
$fetched = $tester->statetransition->getDefinition('story', 0);

/* Case 5: second save → version=2. */
$secondSave = $tester->statetransition->saveDefinition('story', 0, $storyDef, 1, true);

/* Case 6: wrong version → conflict. */
$conflict = $tester->statetransition->saveDefinition('story', 0, $storyDef, 99, true);

/* Case 7: explicitly disabled. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
$disabledSave = $tester->statetransition->saveDefinition('story', 0, $storyDef, 0, false);
$disabledFetched = $tester->statetransition->getDefinition('story', 0);

r($firstSave['ok']) && p() && e('1');
r($firstSave['version']) && p() && e('1');
r($fetched['enabled'] ? '1' : '0') && p() && e('1');
r($fetched['objectType']) && p() && e('story');
r($secondSave['version']) && p() && e('2');
r($conflict['error']) && p() && e('versionConflict');
r($disabledFetched['enabled'] ? '1' : '0') && p() && e('0');
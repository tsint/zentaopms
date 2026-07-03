#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->getStatusList();
timeout=0
cid=0

- 执行$systemList @4
- 执行$storyList @5
- 执行$storyList['draft']) ? '1' : '0 @1
- 执行$storyList['closed']) ? '1' : '0 @1
- 执行$storyList['reviewing']) ? '1' : '0 @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → fallback to system lang list (bug has 3 statuses). */
$systemList = $tester->statetransition->getStatusList('bug', 0);

/* Case 2-5: with definition. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$storyList = $tester->statetransition->getStatusList('story', 0);

r(count($systemList)) && p() && e('4');
r(count($storyList)) && p() && e('5');
r(isset($storyList['draft']) ? '1' : '0') && p() && e('1');
r(isset($storyList['closed']) ? '1' : '0') && p() && e('1');
r(isset($storyList['reviewing']) ? '1' : '0') && p() && e('1');
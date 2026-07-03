#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->getDefaultDefinition();
timeout=0
cid=0

- 执行$story['statuses'] @5
- 执行$story['transitions'] @9
- 执行$story['entries'] @2
- 执行$bug['statuses'] @3
- 执行$task['statuses'] @6
- 执行$epic['statuses'] @5
- 执行$req['statuses'] @5
- 执行$invalid['statuses'] @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$story      = $tester->statetransition->getDefaultDefinition('story');
$bug        = $tester->statetransition->getDefaultDefinition('bug');
$task       = $tester->statetransition->getDefaultDefinition('task');
$epic       = $tester->statetransition->getDefaultDefinition('epic');
$req        = $tester->statetransition->getDefaultDefinition('requirement');
$invalid    = $tester->statetransition->getDefaultDefinition('nonexistent');

r(count($story['statuses'])) && p() && e('5');
r(count($story['transitions'])) && p() && e('9');
r(count($story['entries'])) && p() && e('2');
r(count($bug['statuses'])) && p() && e('3');
r(count($task['statuses'])) && p() && e('6');
r(count($epic['statuses'])) && p() && e('5');
r(count($req['statuses'])) && p() && e('5');
r(count($invalid['statuses'])) && p() && e('0');
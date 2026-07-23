#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->isActionAllowed();
timeout=0
cid=0

- 执行$noDef @1
- 执行$allowedAction @1
- 执行$deniedAction @0
- 执行$unknownAction @0
- 执行$assignToAllowed @1
- 执行$assignToAllowedLower @1
- 执行$bugAssignToAllowed @1
- 执行$taskAssignToAllowed @1
- 执行$disabledTransition @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → allowed (unrestricted). */
$noDef = $tester->statetransition->isActionAllowed('bug', 0, 'active', 'resolve');

/* Case 2-3: enabled definition with matching transition. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$allowedAction = $tester->statetransition->isActionAllowed('story', 0, 'draft', 'submitreview');
$deniedAction  = $tester->statetransition->isActionAllowed('story', 0, 'closed', 'submitreview');
$unknownAction = $tester->statetransition->isActionAllowed('story', 0, 'draft', 'nonexistent_action');
$assignToAllowed = $tester->statetransition->isActionAllowed('story', 0, 'closed', 'assignTo');
$assignToAllowedLower = $tester->statetransition->isActionAllowed('story', 0, 'reviewing', 'assignto');
$bugAssignToAllowed = $tester->statetransition->isActionAllowed('bug', 0, 'closed', 'assignTo');
$taskAssignToAllowed = $tester->statetransition->isActionAllowed('task', 0, 'cancel', 'assignTo');

/* Case 5: disabled transition is not allowed. */
$storyDef['transitions'][0]['enabled'] = false;
$tester->statetransition->saveDefinition('story', 0, $storyDef, 1, true);
$disabledTransition = $tester->statetransition->isActionAllowed('story', 0, 'draft', 'submitreview');

r($noDef) && p() && e('1');
r($allowedAction) && p() && e('1');
r($deniedAction) && p() && e('0');
r($unknownAction) && p() && e('0');
r($assignToAllowed) && p() && e('1');
r($assignToAllowedLower) && p() && e('1');
r($bugAssignToAllowed) && p() && e('1');
r($taskAssignToAllowed) && p() && e('1');
r($disabledTransition) && p() && e('0');

#!/usr/bin/env php
<?php
/**
title=测试 task/bug 业务方法的工作流守卫;
timeout=0
cid=0

- 执行$result1Ok @1
- 执行$result2Ok @1
- 执行$result3Ok @0
- 执行$result4Ok @1
- 执行$result5Ok @1
- 执行$result6Ok @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

/* Suppress HTML warnings from polluting test output (kanban etc. emit <pre> on edge cases). */
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ob_start();

su('admin');

global $tester;
$tester->loadModel('statetransition');
$tester->loadModel('task');
$tester->loadModel('bug');

/* Find or create a 'doing' task to test pause. */
$task = $tester->dao->select('*')->from(TABLE_TASK)->where('status')->eq('doing')->andWhere('deleted')->eq('0')->limit(1)->fetch();
if(empty($task))
{
    $newTask = new stdclass();
    $newTask->project   = 1;
    $newTask->execution = 1;
    $newTask->name      = 'workflow-test-' . time();
    $newTask->type      = 'devel';
    $newTask->status    = 'doing';
    $newTask->openedBy  = 'admin';
    $newTask->openedDate = helper::now();
    $newTask->assignedTo = 'admin';
    $tester->dao->insert(TABLE_TASK)->data($newTask)->exec();
    $taskID = (int)$tester->dao->lastInsertID();
}
else
{
    $taskID = (int)$task->id;
}

/* Find or create an 'active' bug to test resolve. */
$bug = $tester->dao->select('*')->from(TABLE_BUG)->where('status')->eq('active')->andWhere('deleted')->eq('0')->limit(1)->fetch();
if(empty($bug))
{
    $newBug = new stdclass();
    $newBug->product    = 1;
    $newBug->title      = 'workflow-test-' . time();
    $newBug->severity   = 3;
    $newBug->type       = 'codeerror';
    $newBug->status     = 'active';
    $newBug->openedBy   = 'admin';
    $newBug->openedDate = helper::now();
    $newBug->assignedTo = 'admin';
    $tester->dao->insert(TABLE_BUG)->data($newBug)->exec();
    $bugID = (int)$tester->dao->lastInsertID();
}
else
{
    $bugID = (int)$bug->id;
    $bugProduct = (int)$bug->product;
}

$resetState = function() use ($tester, $taskID, $bugID) {
    $tester->dao->update(TABLE_TASK)->set('status')->eq('doing')->where('id')->eq($taskID)->exec();
    $tester->dao->update(TABLE_BUG)->set('status')->eq('active')->where('id')->eq($bugID)->exec();
    $tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
    $tester->statetransition->clearCache();
    dao::$errors = array();
    $_POST = array();
};

/* Case 1: task pause, no definition → fallback, succeeds. */
$resetState();
$taskObj = $tester->dao->select('*')->from(TABLE_TASK)->where('id')->eq($taskID)->fetch();
$taskUpdate = new stdclass();
$taskUpdate->id = $taskID;
$taskUpdate->status = 'pause';
$result1 = $tester->task->pause($taskUpdate);
$result1Ok = $result1 === false ? '0' : '1';

/* Case 2: task pause, workflow allows → succeeds. */
$resetState();
$taskDef = $tester->statetransition->getDefaultDefinition('task');
$tester->statetransition->saveDefinition('task', 0, $taskDef, 0, true);
$taskUpdate = new stdclass();
$taskUpdate->id = $taskID;
$taskUpdate->status = 'pause';
$_POST['comment'] = 'workflow allows';
$result2 = $tester->task->pause($taskUpdate);
$result2Ok = $result2 === false ? '0' : '1';

/* Case 3: task pause, workflow forbids → fails. */
$resetState();
$def3 = $taskDef;
$def3['transitions'] = array_values(array_filter($def3['transitions'], fn($t) => $t['action'] !== 'pause'));
$tester->statetransition->saveDefinition('task', 0, $def3, 0, true);
$taskUpdate = new stdclass();
$taskUpdate->id = $taskID;
$taskUpdate->status = 'pause';
$_POST['comment'] = 'workflow forbids';
$result3 = $tester->task->pause($taskUpdate);
$result3Ok = $result3 === false ? '0' : '1';

/* Case 4: bug resolve, no definition → fallback, succeeds. */
$resetState();
$bugObj = $tester->dao->select('*')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch();
$bugUpdate = new stdclass();
$bugUpdate->id = $bugID;
$bugUpdate->status = 'resolved';
$bugUpdate->resolution = 'fixed';
$bugUpdate->resolvedBuild = 1;
$bugUpdate->resolvedBy = 'admin';
$bugUpdate->resolvedDate = helper::now();
$bugUpdate->assignedTo = $bugObj->assignedTo;
$bugUpdate->product = $bugObj->product;
$result4 = $tester->bug->resolve($bugUpdate);
$result4Ok = $result4 === false ? '0' : '1';

/* Case 5: bug resolve, workflow allows → succeeds. */
$resetState();
$bugDef = $tester->statetransition->getDefaultDefinition('bug');
$tester->statetransition->saveDefinition('bug', 0, $bugDef, 0, true);
$bugObj = $tester->dao->select('*')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch();
$bugUpdate = new stdclass();
$bugUpdate->id = $bugID;
$bugUpdate->status = 'resolved';
$bugUpdate->resolution = 'fixed';
$bugUpdate->resolvedBuild = 1;
$bugUpdate->resolvedBy = 'admin';
$bugUpdate->resolvedDate = helper::now();
$bugUpdate->assignedTo = $bugObj->assignedTo;
$bugUpdate->product = $bugObj->product;
$_POST['comment'] = 'workflow allows';
$result5 = $tester->bug->resolve($bugUpdate);
$result5Ok = $result5 === false ? '0' : '1';

/* Case 6: bug resolve, workflow with resolve removed — still succeeds via auto-injection. */
$resetState();
$def6 = $bugDef;
$def6['transitions'] = array_values(array_filter($def6['transitions'], fn($t) => $t['action'] !== 'resolve'));
$tester->statetransition->saveDefinition('bug', 0, $def6, 0, true);
$bugObj = $tester->dao->select('*')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch();
$bugUpdate = new stdclass();
$bugUpdate->id = $bugID;
$bugUpdate->status = 'resolved';
$bugUpdate->resolution = 'fixed';
$bugUpdate->resolvedBuild = 1;
$bugUpdate->resolvedBy = 'admin';
$bugUpdate->resolvedDate = helper::now();
$bugUpdate->assignedTo = $bugObj->assignedTo;
$bugUpdate->product = $bugObj->product;
$_POST['comment'] = 'workflow allows via auto-injection';
$result6 = $tester->bug->resolve($bugUpdate);
$result6Ok = $result6 === false ? '0' : '1';

/* Cleanup. */
$resetState();

/* Flush and discard any HTML warning output captured during test runs. */
ob_end_clean();

r($result1Ok) && p() && e('1');
r($result2Ok) && p() && e('1');
r($result3Ok) && p() && e('0');
r($result4Ok) && p() && e('1');
r($result5Ok) && p() && e('1');
r($result6Ok) && p() && e('1');

/* Debug any failures. */
if($result2Ok !== '1' || $result4Ok !== '1' || $result5Ok !== '1')
{
    echo "DEBUG unexpected failures detected above.\n";
}
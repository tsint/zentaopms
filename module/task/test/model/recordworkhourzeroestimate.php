#!/usr/bin/env php
<?php
include dirname(__FILE__, 5) . '/test/lib/init.php';
/**
title=测试零预计任务登记实际耗时;
timeout=0
cid=0


*/

$tester->dao->begin();
try
{
    $tester->dao->insert(TABLE_TASK)->data((object)array
    (
        'project'    => 900001,
        'execution'  => 900002,
        'name'       => '零预计任务实际耗时测试',
        'type'       => 'devel',
        'status'     => 'wait',
        'estimate'   => 0,
        'consumed'   => 0,
        'left'       => 0,
        'assignedTo' => 'admin',
        'openedBy'   => 'admin',
        'openedDate' => helper::now(),
        'version'    => 1,
        'vision'     => 'rnd',
        'deleted'    => '0'
    ))->exec();
    $taskID = (int)$tester->dao->lastInsertID();

    $tester->app->user = (object)array('account' => 'admin', 'admin' => true);
    $record = (object)array('date' => helper::today(), 'work' => '实际处理工作', 'consumed' => 3, 'left' => '');
    $tester->loadModel('task')->recordWorkhour($taskID, array(1 => $record));

    $task   = $tester->dao->select('consumed,`left`')->from(TABLE_TASK)->where('id')->eq($taskID)->fetch();
    $effort = $tester->dao->select('consumed,`left`')->from(TABLE_EFFORT)->where('objectType')->eq('task')->andWhere('objectID')->eq($taskID)->fetch();

    $tester->dao->insert(TABLE_TASK)->data((object)array
    (
        'project'    => 900001,
        'execution'  => 900002,
        'name'       => '非零预计超支测试',
        'type'       => 'devel',
        'status'     => 'wait',
        'estimate'   => 2,
        'consumed'   => 0,
        'left'       => 2,
        'assignedTo' => 'admin',
        'openedBy'   => 'admin',
        'openedDate' => helper::now(),
        'version'    => 1,
        'vision'     => 'rnd',
        'deleted'    => '0'
    ))->exec();
    $overrunTaskID = (int)$tester->dao->lastInsertID();
    $overrunRecord = (object)array('date' => helper::today(), 'work' => '超出预计', 'consumed' => 3, 'left' => '');
    $tester->task->recordWorkhour($overrunTaskID, array(1 => $overrunRecord));
    $overrunEffort = $tester->dao->select('consumed,`left`')->from(TABLE_EFFORT)->where('objectType')->eq('task')->andWhere('objectID')->eq($overrunTaskID)->fetch();

    dao::$errors = array();
    $zeroNegative = $tester->task->checkWorkhour((object)array('id' => $taskID, 'team' => array(), 'estimate' => 0, 'consumed' => 3), array(1 => (object)array('date' => helper::today(), 'work' => '负剩余', 'consumed' => 1, 'left' => -1))) === false;
    dao::$errors = array();
    $estimatedNegative = $tester->task->checkWorkhour((object)array('id' => $overrunTaskID, 'team' => array(), 'estimate' => 2, 'consumed' => 3), array(1 => (object)array('date' => helper::today(), 'work' => '负剩余', 'consumed' => 1, 'left' => -2))) !== false;

    r($task) && p('consumed') && e('3.00');
    r($task) && p('left') && e('0.00');
    r($effort) && p('consumed,left') && e('3.00,0.00');
    r($overrunEffort) && p('consumed,left') && e('3.00,-1.00');
    r($zeroNegative) && p() && e('1');
    r($estimatedNegative) && p() && e('1');
}
finally
{
    $tester->dao->rollBack();
}
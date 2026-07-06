#!/usr/bin/env php
<?php
include dirname(__FILE__, 5) . '/test/lib/init.php';
/**
title=测试 taskModel 创建任务时清理同ID孤儿版本;
timeout=0
cid=0


*/

$taskID = random_int(15000000, 16000000);
while($tester->dao->select('id')->from(TABLE_TASK)->where('id')->eq($taskID)->fetch('id') || $tester->dao->select('task')->from(TABLE_TASKSPEC)->where('task')->eq($taskID)->fetch('task')) $taskID = random_int(15000000, 16000000);
$autoIncrement = $tester->dao->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zt_task'")->fetchColumn();
try
{
    $tester->dao->insert(TABLE_TASKSPEC)->data((object)array('task' => $taskID, 'version' => 1, 'name' => '旧孤儿版本'))->exec();

    $tester->app->user = (object)array('account' => 'admin', 'admin' => true);
    $task = (object)array
    (
        'id'         => $taskID,
        'project'    => 900001,
        'execution'  => 900002,
        'module'     => 0,
        'story'      => 0,
        'name'       => '孤儿版本回归测试',
        'type'       => 'devel',
        'status'     => 'wait',
        'pri'        => 3,
        'estimate'   => 1,
        'left'       => 1,
        'assignedTo' => '',
        'openedBy'   => 'admin',
        'openedDate' => helper::now(),
        'version'    => 1,
        'vision'     => 'rnd'
    );

    $taskModel = $tester->loadModel('task');
    $createdID = $taskModel->create($task, false);
    $taskSpec  = $tester->dao->select('name')->from(TABLE_TASKSPEC)->where('task')->eq($taskID)->andWhere('version')->eq(1)->fetch();
    $specCount = $tester->dao->select('COUNT(*) AS count')->from(TABLE_TASKSPEC)->where('task')->eq($taskID)->fetch('count');

    r($createdID == $taskID) && p() && e('1');
    r($taskSpec) && p('name') && e('孤儿版本回归测试');
    r($specCount) && p() && e('1');
}
finally
{
    $tester->dao->delete()->from(TABLE_TASKSPEC)->where('task')->eq($taskID)->exec();
    $tester->dao->delete()->from(TABLE_TASK)->where('id')->eq($taskID)->exec();
    if($autoIncrement) $tester->dao->query('ALTER TABLE ' . TABLE_TASK . ' AUTO_INCREMENT = ' . (int)$autoIncrement);
}
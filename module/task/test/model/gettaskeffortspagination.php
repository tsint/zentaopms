#!/usr/bin/env php
<?php
include dirname(__FILE__, 5) . '/test/lib/init.php';

/**

title=测试 taskModel 任务工时数据库分页;
timeout=0
cid=task

- 第一页只返回10条 @10
- 第二页只返回1条 @1
- 分页总数为11条 @11
- 两页无重复且覆盖全部记录 @11

*/

$objectID = random_int(15000000, 16000000);
$tester->dao->begin();
try
{
    for($i = 1; $i <= 11; $i ++)
    {
        $tester->dao->insert(TABLE_EFFORT)->data((object)array
        (
            'objectType' => 'task',
            'objectID'   => $objectID,
            'account'    => 'admin',
            'date'       => '2026-06-30',
            'consumed'   => 1,
            'left'       => 0,
            'work'       => "分页工时{$i}",
            'deleted'    => '0'
        ))->exec();
    }

    $tester->app->rawModule = 'task';
    $tester->app->rawMethod = 'recordWorkhour';
    $tester->app->loadClass('pager', true);
    $taskModel  = $tester->loadModel('task');
    $firstPager = new pager(0, 10, 1, 'taskEffortTest');
    $firstPage  = $taskModel->getTaskEfforts($objectID, '', 0, 'id_desc', $firstPager);
    $nextPager  = new pager($firstPager->recTotal, 10, 2, 'taskEffortTest');
    $nextPage   = $taskModel->getTaskEfforts($objectID, '', 0, 'id_desc', $nextPager);
    $allIDs     = array_merge(array_keys($firstPage), array_keys($nextPage));

    r(count($firstPage)) && p() && e('10');
    r(count($nextPage)) && p() && e('1');
    r($firstPager->recTotal) && p() && e('11');
    r(count(array_unique($allIDs))) && p() && e('11');
}
finally
{
    $tester->dao->rollBack();
}

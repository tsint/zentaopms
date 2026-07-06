#!/usr/bin/env php
<?php
include dirname(__FILE__, 6) . '/test/lib/init.php';
/**
title=测试 objecteffort 初始预计与普通工时校验隔离;
timeout=0
cid=0


*/

if(!defined('TABLE_OBJECTEFFORT')) define('TABLE_OBJECTEFFORT', '`zt_objecteffort`');

$tester->dao->begin();
try
{
    $tester->dao->insert(TABLE_BUG)->data((object)array
    (
        'product'    => 0,
        'title'      => '初始预计测试 Bug',
        'status'     => 'active',
        'openedBy'   => 'admin',
        'openedDate' => helper::now(),
        'deleted'    => '0'
    ))->exec();
    $bugID = (int)$tester->dao->lastInsertID();

    $tester->app->user = (object)array('account' => 'admin', 'admin' => true);
    $model = $tester->loadModel('objecteffort');
    $zeroEffort = (object)array('date' => helper::today(), 'estimate' => 8, 'consumed' => 0, 'left' => 8, 'work' => '普通零耗时');
    $normalRejected = $model->record('bug', $bugID, $zeroEffort) === false;
    dao::$errors = array();

    $effortID = $model->initializeEstimate('bug', $bugID, 8);
    $effort   = $model->getByID((int)$effortID);
    $overrunID = $model->record('bug', $bugID, (object)array('date' => helper::today(), 'consumed' => 10, 'left' => '', 'work' => '超出预计'));
    $overrun   = $model->getByID((int)$overrunID);

    $tester->dao->insert(TABLE_BUG)->data((object)array('product' => 0, 'title' => '零预计 Bug', 'status' => 'active', 'openedBy' => 'admin', 'openedDate' => helper::now(), 'deleted' => '0'))->exec();
    $zeroBugID = (int)$tester->dao->lastInsertID();
    dao::$errors = array();
    $zeroNegative = $model->record('bug', $zeroBugID, (object)array('date' => helper::today(), 'consumed' => 1, 'left' => -1, 'work' => '负剩余')) === false;
    dao::$errors = array();

    $tester->dao->insert(TABLE_STORY)->data((object)array('product' => 0, 'type' => 'story', 'title' => '非零预计需求', 'status' => 'active', 'estimate' => 8, 'openedBy' => 'admin', 'openedDate' => helper::now(), 'version' => 1, 'deleted' => '0'))->exec();
    $storyID = (int)$tester->dao->lastInsertID();
    dao::$errors = array();
    $storyEffortID = $model->record('story', $storyID, (object)array('date' => helper::today(), 'consumed' => 10, 'left' => '', 'work' => '需求超出预计'));
    $storyEffort   = $model->getByID((int)$storyEffortID);

    $tester->dao->insert(TABLE_STORY)->data((object)array('product' => 0, 'type' => 'requirement', 'title' => '零预计用户需求', 'status' => 'active', 'estimate' => 0, 'openedBy' => 'admin', 'openedDate' => helper::now(), 'version' => 1, 'deleted' => '0'))->exec();
    $zeroStoryID = (int)$tester->dao->lastInsertID();
    $zeroStoryEffortID = $model->record('requirement', $zeroStoryID, (object)array('date' => helper::today(), 'consumed' => 3, 'left' => '', 'work' => '零预计需求耗时'));
    $zeroStoryEffort   = $model->getByID((int)$zeroStoryEffortID);

    $batchStoryID = $zeroStoryID + 1;
    $tester->dao->insert(TABLE_STORY)->data((object)array('id' => $batchStoryID, 'product' => 0, 'type' => 'story', 'title' => '批量超支需求', 'status' => 'active', 'estimate' => 5, 'openedBy' => 'admin', 'openedDate' => helper::now(), 'version' => 1, 'deleted' => '0'))->exec();
    $batchIDs = $model->recordBatch('story', $batchStoryID, array(
        (object)array('date' => helper::today(), 'consumed' => 3, 'left' => '', 'work' => '批量第一条'),
        (object)array('date' => helper::today(), 'consumed' => 4, 'left' => '', 'work' => '批量第二条')
    ));
    $batchLast = $model->getByID((int)end($batchIDs));

    r($normalRejected) && p() && e('1');
    r($effortID > 0) && p() && e('1');
    r($effort) && p('estimate,consumed,left') && e('8.00,0.00,8.00');
    r($overrun) && p('consumed,left') && e('10.00,-2.00');
    r($zeroNegative) && p() && e('1');
    r($storyEffort) && p('consumed,left') && e('10.00,-2.00');
    r($zeroStoryEffort) && p('consumed,left') && e('3.00,0.00');
    r($batchLast) && p('estimate,consumed,left') && e('5.00,4.00,-2.00');
}
finally
{
    $tester->dao->rollBack();
}
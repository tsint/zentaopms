#!/usr/bin/env php
<?php
/**
title=测试 storyModel->recallReview();
timeout=0
cid=18577

- 需求99028状态属性status @draft
- 需求99029状态属性status @changing
- 撤回后，需求99028版本3的评审记录不存在 @0
- 撤回后，需求99029版本3的评审记录不存在 @0
- 撤回评审不遗留输出缓冲 @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
su('admin');

global $tester;
$tester->loadModel('story');

$storyIDs = '99028,99029';
$tester->dao->delete()->from(TABLE_STORYREVIEW)->where('story')->in($storyIDs)->exec();
$tester->dao->delete()->from(TABLE_STORYSPEC)->where('story')->in($storyIDs)->exec();
$tester->dao->delete()->from(TABLE_STORY)->where('id')->in($storyIDs)->exec();

$stories = array(
    (object)array('id' => 99028, 'root' => 99028, 'path' => ',99028,', 'product' => 1, 'title' => 'Recall draft story',    'type' => 'story', 'status' => '', 'version' => 3, 'openedBy' => 'admin', 'openedDate' => '2026-07-17 00:00:00'),
    (object)array('id' => 99029, 'root' => 99029, 'path' => ',99029,', 'product' => 1, 'title' => 'Recall changing story', 'type' => 'story', 'status' => '', 'version' => 3, 'changedBy' => 'admin', 'openedBy' => 'admin', 'openedDate' => '2026-07-17 00:00:00')
);
foreach($stories as $story) $tester->dao->insert(TABLE_STORY)->data($story)->exec();

foreach(array(99028, 99029) as $storyID)
{
    $tester->dao->insert(TABLE_STORYSPEC)->data((object)array('story' => $storyID, 'version' => 3, 'title' => "Story $storyID", 'spec' => '', 'verify' => ''))->exec();
    $tester->dao->insert(TABLE_STORYREVIEW)->data((object)array('story' => $storyID, 'version' => 3, 'reviewer' => 'admin', 'result' => 'pass'))->exec();
}

$bufferLevel = ob_get_level();
$tester->story->recallReview(99028);
$tester->story->recallReview(99029);
$bufferClean = ob_get_level() === $bufferLevel;

$storyList       = $tester->story->dao->select('*')->from(TABLE_STORY)->where('id')->in($storyIDs)->fetchAll('id');
$storyReviewList = $tester->story->dao->select('*')->from(TABLE_STORYREVIEW)->where('story')->in($storyIDs)->fetchGroup('story', 'version');

r($storyList[99028]) && p('status') && e('draft');    // 需求99028状态
r($storyList[99029]) && p('status') && e('changing'); // 需求99029状态
r((int)isset($storyReviewList[99028][3])) && p() && e('0'); // 撤回后，需求99028版本3的评审记录不存在
r((int)isset($storyReviewList[99029][3])) && p() && e('0'); // 撤回后，需求99029版本3的评审记录不存在
r((int)$bufferClean) && p() && e('1'); // 撤回评审不遗留输出缓冲

$tester->dao->delete()->from(TABLE_STORYREVIEW)->where('story')->in($storyIDs)->exec();
$tester->dao->delete()->from(TABLE_STORYSPEC)->where('story')->in($storyIDs)->exec();
$tester->dao->delete()->from(TABLE_STORY)->where('id')->in($storyIDs)->exec();

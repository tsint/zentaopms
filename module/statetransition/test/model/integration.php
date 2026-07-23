#!/usr/bin/env php
<?php
/**
title=测试业务模块工作流守卫（applyWorkflowTransition 通过 story close）;
timeout=0
cid=0

- 执行$status1 @closed
- 执行$status2 @closed
- 执行$result3Ok @0
- 执行$status4 @closed
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

ob_start();
su('admin');

global $tester, $config;
$tester->loadModel('statetransition');
$tester->loadModel('story');
if(!isset($config->mail)) $config->mail = new stdclass();
$config->mail->turnon = false;

/* Find or create a known active story in a product WITHOUT product-scope workflow override.
   Product-scope definitions (e.g. product 4) override global, breaking test isolation.
   Use product=1 explicitly to guarantee isolation. */
$story = $tester->dao->select('*')->from(TABLE_STORY)
    ->where('status')->eq('active')
    ->andWhere('type')->eq('story')
    ->andWhere('deleted')->eq('0')
    ->andWhere('product')->eq(1)
    ->limit(1)->fetch();
if(empty($story))
{
    $newStory = new stdclass();
    $newStory->product  = 1;
    $newStory->branch   = 0;
    $newStory->type     = 'story';
    $newStory->title    = 'workflow-test-' . time();
    $newStory->status   = 'active';
    $newStory->openedBy = 'admin';
    $newStory->openedDate = helper::now();
    $newStory->version  = 1;
    $tester->dao->insert(TABLE_STORY)->data($newStory)->exec();
    $storyID = (int)$tester->dao->lastInsertID();
}
else
{
    $storyID = (int)$story->id;
}

$defaultDef = $tester->statetransition->getDefaultDefinition('story');

/* Helper: fully reset story + workflow state. */
$resetToActive = function() use ($tester, $storyID) {
    $storyProduct = (int)$tester->dao->select('product')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch('product');
    $tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();
    $tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
    $tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)
        ->where('scope')->eq('product')
        ->andWhere('productID')->eq($storyProduct)
        ->andWhere('objectType')->eq('story')
        ->exec();
    $tester->statetransition->clearCache();
    dao::$errors = array();
    $_POST = array();
};

/* Case 1: no definition → fallback to business default 'closed'. */
$resetToActive();
$postData1 = new stdclass();
$postData1->closedReason = 'done';
$postData1->status = 'closed';
$_POST['comment'] = 'no definition case';
$tester->story->close($storyID, $postData1);
$fetched1 = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$status1 = $fetched1 ? $fetched1->status : 'MISSING';

/* Case 2: enable workflow, allow close. */
$resetToActive();
$tester->statetransition->saveDefinition('story', 0, $defaultDef, 0, true);
$postData2 = new stdclass();
$postData2->closedReason = 'done';
$postData2->status = 'closed';
$_POST['comment'] = 'workflow allows close';
$tester->story->close($storyID, $postData2);
$fetched2 = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$status2 = $fetched2 ? $fetched2->status : 'MISSING';

/* Case 3: workflow enabled but close transition removed — close must be blocked by the custom definition. */
$resetToActive();
$def3 = $defaultDef;
$def3['transitions'] = array_values(array_filter($def3['transitions'], fn($t) => $t['action'] !== 'close'));
$tester->statetransition->saveDefinition('story', 0, $def3, 0, true);
$postData3 = new stdclass();
$postData3->closedReason = 'done';
$postData3->status = 'closed';
$_POST['comment'] = 'workflow blocks close when transition removed';
$result3 = $tester->story->close($storyID, $postData3);
$result3Ok = $result3 === false ? '0' : '1';

/* Case 4: disable workflow via saveDefinition(enabled=false) → restore fallback. */
$resetToActive();
$tester->statetransition->saveDefinition('story', 0, $defaultDef, 0, false);
$postData4 = new stdclass();
$postData4->closedReason = 'done';
$postData4->status = 'closed';
$_POST['comment'] = 'workflow disabled';
$tester->story->close($storyID, $postData4);
$fetched4 = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$status4 = $fetched4 ? $fetched4->status : 'MISSING';

/* Cleanup. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
$tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();

ob_end_clean();

r($status1) && p() && e('closed');
r($status2) && p() && e('closed');
r($result3Ok) && p() && e('0');
r($status4) && p() && e('closed');

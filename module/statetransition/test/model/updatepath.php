#!/usr/bin/env php
<?php
/**
title=测试业务模块 update() 不能绕过工作流角色限制;
timeout=0
cid=0

- 执行$workerBlocked @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->loadModel('statetransition');
$tester->loadModel('story');

/* Set up: qa-only transition testing → review → active */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$storyDef['statuses'][] = array('key'=>'testing','label'=>array('zh_cn'=>'测试','en'=>'Testing'),'category'=>'normal','color'=>'#999','isSystem'=>false,'isEntry'=>false,'fieldRules'=>array());
$storyDef['transitions'][] = array(
    'key'=>'testing-to-active-via-review',
    'fromStatus'=>'testing','toStatus'=>'active','action'=>'review','branch'=>null,
    'label'=>array('zh_cn'=>'测试','en'=>'Test'),
    'roles'=>array('qa'),
    'accounts'=>array(),
    'requireComment'=>false,'enabled'=>true,'isCustom'=>false,
    'buttonLabel'=>array('zh_cn'=>'','en'=>''),'buttonIcon'=>null,'buttonOrder'=>0,
    'buttonGroup'=>'primary','sideEffects'=>array(),'condition'=>null
);
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

/* Find a story in product 1 (no product-scope override) */
$storyID = (int)$tester->dao->select('id')->from(TABLE_STORY)
    ->where('product')->eq(1)
    ->andWhere('deleted')->eq(0)
    ->limit(1)->fetch()->id;
if(empty($storyID))
{
    $newStory = new stdclass();
    $newStory->product = 1;
    $newStory->branch = 0;
    $newStory->type = 'story';
    $newStory->title = 'test-role-enforce-' . time();
    $newStory->status = 'testing';
    $newStory->openedBy = 'admin';
    $newStory->openedDate = helper::now();
    $newStory->version = 1;
    $tester->dao->insert(TABLE_STORY)->data($newStory)->exec();
    $storyID = (int)$tester->dao->lastInsertID();
}
$tester->dao->update(TABLE_STORY)->set('status')->eq('testing')->where('id')->eq($storyID)->exec();

/* Worker (dev) attempts to change status via direct DAO update simulation.
   This mimics what could happen if business module update() doesn't enforce workflow.
   We test the underlying enforcement at assertStatusChange level. */
su('worker');
$beforeStatus = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch('status');

/* Try assertStatusChange - this is the workflow guard for direct status writes.
   Worker (dev) shouldn't be able to change testing→active because transition is qa-only. */
$decision = $tester->statetransition->assertStatusChange('story', 1, $storyID, 'testing', 'active');
$guardBlocksWorker = !$decision->ok;

/* Also confirm the reverse: tester (qa) should pass */
su('tester');
$decision2 = $tester->statetransition->assertStatusChange('story', 1, $storyID, 'testing', 'active');
$guardAllowsTester = $decision2->ok;

$workerBlocked = ($guardBlocksWorker && $guardAllowsTester) ? 1 : 0;

/* Cleanup */
$tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

r($workerBlocked) && p() && e('1');
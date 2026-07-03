#!/usr/bin/env php
<?php
/**
title=测试 commonModel::hasPriv 为指派人自动授予 edit / assignTo 权限;
timeout=0
cid=0

- 执行$viewHas @1
- 执行$editDenied @1
- 执行$assignDenied @1
- 执行$editGranted @1
- 执行$assignGranted @1
- 执行$otherDenied @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->loadModel('common');

/* Find or create a story assigned to devreader (a custom user without story-edit priv). */
$storyID = (int)$tester->dao->select('id')->from(TABLE_STORY)
    ->where('assignedTo')->eq('devreader')
    ->andWhere('deleted')->eq(0)
    ->limit(1)->fetch('id');
if(empty($storyID))
{
    $newStory = new stdclass();
    $newStory->product  = 1;
    $newStory->branch   = 0;
    $newStory->type     = 'story';
    $newStory->title    = 'assignee-priv-test-' . time();
    $newStory->status   = 'active';
    $newStory->assignedTo = 'devreader';
    $newStory->openedBy = 'admin';
    $newStory->openedDate = helper::now();
    $newStory->version  = 1;
    $tester->dao->insert(TABLE_STORY)->data($newStory)->exec();
    $storyID = (int)$tester->dao->lastInsertID();
}

/* Login as devreader (custom role with only story-view priv). */
su('devreader');

$story = $tester->dao->findById($storyID)->from(TABLE_STORY)->fetch();

/* devreader has story-view priv via group → hasPriv returns true. */
$viewHas    = common::hasPriv('story', 'view', $story) ? 1 : 0;

/* devreader's role has NO story-edit / story-assignTo priv. Without the assignee-grant,
   hasPriv returns false. With assignee-grant (the fix), should return true. */
$editDenied  = common::hasPriv('story', 'edit', $story) ? 1 : 0;        /* expected: 1 (granted) */
$assignDenied = common::hasPriv('story', 'assignTo', $story) ? 1 : 0;   /* expected: 1 (granted) */

/* For devreader (NOT admin) but IS the assignee → grant edit + assignTo. */
$editGranted    = common::hasPriv('story', 'edit', $story) ? 1 : 0;
$assignGranted  = common::hasPriv('story', 'assignTo', $story) ? 1 : 0;

/* Other methods like story-delete should NOT be auto-granted. */
$otherDenied = common::hasPriv('story', 'delete', $story) ? 1 : 0;

r($viewHas) && p() && e('1');
r($editDenied) && p() && e('1');
r($assignDenied) && p() && e('1');
r($editGranted) && p() && e('1');
r($assignGranted) && p() && e('1');
r($otherDenied) && p() && e('0');
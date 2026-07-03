#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->assertEntryState();
timeout=0
cid=0

- 执行$noDef @custom_status
- 执行$inEntries @active
- 执行$notInEntries @draft
- 执行$notInEntries2 @draft
- 执行$invalidType @whatever
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → unchanged. */
$noDef = $tester->statetransition->assertEntryState('bug', 0, 'custom_status');

/* Case 2-3: definition with entries=['draft','active']. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$inEntries     = $tester->statetransition->assertEntryState('story', 0, 'active');
$notInEntries  = $tester->statetransition->assertEntryState('story', 0, 'reviewing');
$notInEntries2 = $tester->statetransition->assertEntryState('story', 0, 'changing');

/* Case 4: invalid objectType. */
$invalidType = $tester->statetransition->assertEntryState('nonexistent', 0, 'whatever');

r($noDef) && p() && e('custom_status');
r($inEntries) && p() && e('active');
r($notInEntries) && p() && e('draft');
r($notInEntries2) && p() && e('draft');
r($invalidType) && p() && e('whatever');
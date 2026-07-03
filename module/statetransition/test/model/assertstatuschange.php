#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->assertStatusChange();
timeout=0
cid=0

- 执行$unrestricted->wasUnrestricted @1
- 执行$legal->ok @1
- 执行$legal->toStatus @active
- 执行$illegal->errorKey @transitionNotFound
- 执行$requireCommentMissing->errorKey @commentRequired
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → unrestricted. */
$unrestricted = $tester->statetransition->assertStatusChange('bug', 0, 1, 'active', 'closed');

/* Case 2-3: legal direct change for story (draft→active exists in transitions for action review). */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$legal = $tester->statetransition->assertStatusChange('story', 0, 1, 'reviewing', 'active');

/* Case 4: illegal direct change. */
$illegal = $tester->statetransition->assertStatusChange('story', 0, 1, 'draft', 'closed');

/* Case 5: transition with requireComment. */
$requireCommentMissing = $tester->statetransition->assertStatusChange('story', 0, 1, 'reviewing', 'closed');

r($unrestricted->wasUnrestricted) && p() && e('1');
r($legal->ok) && p() && e('1');
r($legal->toStatus) && p() && e('active');
r($illegal->errorKey) && p() && e('transitionNotFound');
r($requireCommentMissing->errorKey) && p() && e('commentRequired');
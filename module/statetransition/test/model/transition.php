#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->transition();
timeout=0
cid=0

- 执行$bugDecision->wasUnrestricted @1
- 执行$draftToReviewing->ok @1
- 执行$draftToReviewing->toStatus @reviewing
- 执行$reviewPass->toStatus @active
- 执行$reviewRejectNoComment->errorKey @commentRequired
- 执行$reviewRejectWithComment->toStatus @closed
- 执行$ambiguous->errorKey @ambiguousBranch
- 执行$unknownFrom->errorKey @transitionNotFound
- 执行$invalidType->errorKey @objectTypeInvalid
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

/* Clean and seed workflow_definition for story (global). */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

/* Case 1: unrestricted when no enabled definition (bug still empty). */
$bugDecision = $tester->statetransition->transition('bug', 0, 1, 'active', 'resolve');

/* Case 2-3: normal single-branch transition. */
$draftToReviewing = $tester->statetransition->transition('story', 0, 1, 'draft', 'submitreview');

/* Case 4: review+pass branch. */
$reviewPass = $tester->statetransition->transition('story', 0, 1, 'reviewing', 'review', 'pass');

/* Case 5: review+reject requires comment. */
$reviewRejectNoComment = $tester->statetransition->transition('story', 0, 1, 'reviewing', 'review', 'reject');

/* Case 6: review+reject with comment. */
$reviewRejectWithComment = $tester->statetransition->transition('story', 0, 1, 'reviewing', 'review', 'reject', 'duplicate');

/* Case 7: ambiguous branch when multiple branches exist and caller didn't specify. */
$ambiguous = $tester->statetransition->transition('story', 0, 1, 'reviewing', 'review');

/* Case 8: unknown fromStatus. */
$unknownFrom = $tester->statetransition->transition('story', 0, 1, 'nonexistent', 'submitreview');

/* Case 9: invalid objectType. */
$invalidType = $tester->statetransition->transition('nonexistent', 0, 1, 'draft', 'submitreview');

r($bugDecision->wasUnrestricted) && p() && e('1');
r($draftToReviewing->ok) && p() && e('1');
r($draftToReviewing->toStatus) && p() && e('reviewing');
r($reviewPass->toStatus) && p() && e('active');
r($reviewRejectNoComment->errorKey) && p() && e('commentRequired');
r($reviewRejectWithComment->toStatus) && p() && e('closed');
r($ambiguous->errorKey) && p() && e('ambiguousBranch');
r($unknownFrom->errorKey) && p() && e('transitionNotFound');
r($invalidType->errorKey) && p() && e('objectTypeInvalid');
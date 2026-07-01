<?php
$workflowOldStory = $this->dao->findById($storyID)->from(TABLE_STORY)->fetch();
if(!$workflowOldStory) return false;
$workflowResult = isset($story->result) ? $story->result : '';
$workflowTarget = $workflowOldStory->status;
if($workflowResult == 'pass' || $workflowResult == 'revert') $workflowTarget = 'active';
if($workflowResult == 'reject') $workflowTarget = 'closed';
if($workflowResult == 'clarify') $workflowTarget = $workflowOldStory->changedBy ? 'changing' : 'draft';
$workflowType = $workflowOldStory->type == 'requirement' ? 'requirement' : 'story';
if($workflowTarget != $workflowOldStory->status && !$this->loadModel('workflowflowchart')->checkTransition($workflowType, $storyID, $workflowOldStory->status, $workflowTarget, 'review', $comment)) return false;

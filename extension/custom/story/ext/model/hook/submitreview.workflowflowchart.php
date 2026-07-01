<?php
$workflowOldStory = $this->dao->findById($storyID)->from(TABLE_STORY)->fetch();
if(!$workflowOldStory) return false;
$workflowTarget = !empty($story->reviewer) ? 'reviewing' : $workflowOldStory->status;
$workflowType = $workflowOldStory->type == 'requirement' ? 'requirement' : 'story';
if($workflowTarget != $workflowOldStory->status && !$this->loadModel('workflowflowchart')->checkTransition($workflowType, $storyID, $workflowOldStory->status, $workflowTarget, 'submitreview')) return false;

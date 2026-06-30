<?php
$workflowOldStory = $this->getById($storyID);
$workflowTarget = isset($story->status) ? $story->status : $workflowOldStory->status;
$workflowType = $workflowOldStory->type == 'requirement' ? 'requirement' : 'story';
$workflowComment = isset($story->comment) ? (string)$story->comment : (isset($this->post->comment) ? (string)$this->post->comment : '');
if($workflowTarget != $workflowOldStory->status && !$this->loadModel('workflowflowchart')->checkTransition($workflowType, $storyID, $workflowOldStory->status, $workflowTarget, 'change', $workflowComment)) return false;

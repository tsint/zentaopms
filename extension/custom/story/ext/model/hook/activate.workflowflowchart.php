<?php
$workflowOldStory = $this->dao->findById($storyID)->from(TABLE_STORY)->fetch();
$workflowTarget = $this->getActivateStatus($storyID);
$workflowType = $workflowOldStory->type == 'requirement' ? 'requirement' : 'story';
$workflowComment = isset($postData->comment) ? (string)$postData->comment : (isset($this->post->comment) ? (string)$this->post->comment : '');
if(!$this->loadModel('workflowflowchart')->checkTransition($workflowType, $storyID, $workflowOldStory->status, $workflowTarget, 'activate', $workflowComment)) return false;

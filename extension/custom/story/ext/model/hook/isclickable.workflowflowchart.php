<?php
$workflowAction = strtolower($action);
if($workflowAction == 'recall') $workflowAction = $story->status == 'changing' ? 'recallchange' : 'recallreview';
if(in_array($workflowAction, array('review', 'change', 'recallreview', 'recallchange', 'close', 'activate'), true))
{
    $workflowType = $story->type == 'requirement' ? 'requirement' : 'story';
    $workflowStoryModel = new self();
    if(!$workflowStoryModel->loadModel('workflowflowchart')->isActionAllowed($workflowType, (string)$story->status, $workflowAction)) return false;
}

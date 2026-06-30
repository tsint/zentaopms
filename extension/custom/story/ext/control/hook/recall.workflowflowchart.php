<?php
if($confirm == 'yes')
{
    $workflowStory = $this->story->fetchById($storyID);
    $workflowType = $workflowStory->type == 'requirement' ? 'requirement' : 'story';
    $workflowAction = $workflowStory->status == 'changing' ? 'recallchange' : 'recallreview';
    $workflowTarget = $workflowStory->status == 'changing' ? 'active' : ($workflowStory->changedBy ? 'changing' : 'draft');
    if(!$this->loadModel('workflowflowchart')->checkTransition($workflowType, $storyID, $workflowStory->status, $workflowTarget, $workflowAction))
    {
        return $this->send(array('result' => 'fail', 'message' => dao::getError()));
    }
}

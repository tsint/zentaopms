<?php
$workflowAction = strtolower($action);
if(in_array($workflowAction, array('resolve', 'close', 'activate'), true))
{
    $workflowBugModel = new self();
    if(!$workflowBugModel->loadModel('workflowflowchart')->isActionAllowed('bug', (string)$object->status, $workflowAction)) return false;
}

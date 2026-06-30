<?php
global $app;

$workflowType   = isset($story->type) && in_array($story->type, array('epic', 'requirement'), true) ? $story->type : 'story';
$workflowStatus = isset($story->status) ? (string)$story->status : '';
echo $app->control->loadModel('workflowflowchart')->renderFlowHtml($workflowType, $workflowStatus, true); /* workflowflowchart-detail */

<?php
global $app;

$workflowType   = isset($story->type) && in_array($story->type, array('epic', 'requirement'), true) ? $story->type : 'story';
$workflowStatus = isset($story->status) ? (string)$story->status : '';
$model = $app->control->loadModel('workflowflowchart');
echo $model->renderFlowHtml($workflowType, $workflowStatus, true);

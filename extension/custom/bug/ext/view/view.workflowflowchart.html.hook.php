<?php
global $app;

$workflowStatus = isset($bug->status) ? (string)$bug->status : '';
echo $app->control->loadModel('workflowflowchart')->renderFlowHtml('bug', $workflowStatus, true); /* workflowflowchart-detail */

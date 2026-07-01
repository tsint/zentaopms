<?php
$lang->workflowflowchart = new stdclass();
$lang->workflowflowchart->common         = 'State Flowchart';
$lang->workflowflowchart->manage         = 'Flow Configuration';
$lang->workflowflowchart->browse         = 'View Flow';
$lang->workflowflowchart->enabled        = 'Enforce transitions';
$lang->workflowflowchart->enabledTip     = 'When enabled, transitions not defined in this flowchart are rejected.';
$lang->workflowflowchart->addTransition  = 'Add Transition';
$lang->workflowflowchart->transitionRule = 'Transition Rule';
$lang->workflowflowchart->source         = 'Source State';
$lang->workflowflowchart->target         = 'Target State';
$lang->workflowflowchart->action         = 'Action';
$lang->workflowflowchart->label          = 'Label';
$lang->workflowflowchart->roles          = 'Allowed Roles';
$lang->workflowflowchart->accounts       = 'Allowed Users';
$lang->workflowflowchart->requireComment = 'Require Comment';
$lang->workflowflowchart->description    = 'Description';
$lang->workflowflowchart->edgeEnabled    = 'Enable Transition';
$lang->workflowflowchart->deleteEdge     = 'Delete Transition';
$lang->workflowflowchart->reset          = 'Reset Defaults';
$lang->workflowflowchart->save           = 'Save Flow';
$lang->workflowflowchart->selectEdge     = 'Select an edge to edit its rule';
$lang->workflowflowchart->allActors      = 'When no role or user is selected, anyone with action permission can execute it.';
$lang->workflowflowchart->readonly       = 'Read-only Flowchart';
$lang->workflowflowchart->currentStatus  = 'Current Status';
$lang->workflowflowchart->configure      = 'Configure Flow';
$lang->workflowflowchart->transitionList = 'Transitions';
$lang->workflowflowchart->nodeMatrix     = 'State Nodes';
$lang->workflowflowchart->ruleMatrix     = 'Transition Rules';
$lang->workflowflowchart->flowEmpty      = 'No enabled transitions.';

$lang->workflowflowchart->objectTypeList = array('epic' => 'Epic', 'requirement' => 'Requirement', 'story' => 'Story', 'bug' => 'Bug', 'task' => 'Task', 'testcase' => 'Test Case');
$lang->workflowflowchart->actionList = array(
    'resolve' => 'Resolve', 'close' => 'Close', 'activate' => 'Activate', 'submitreview' => 'Submit Review',
    'review' => 'Review', 'change' => 'Change', 'recallreview' => 'Recall Review', 'recallchange' => 'Recall Change',
    'start' => 'Start', 'restart' => 'Restart', 'pause' => 'Pause', 'finish' => 'Finish', 'cancel' => 'Cancel',
    'edit' => 'Edit', 'block' => 'Block', 'investigate' => 'Investigate'
);

$lang->workflowflowchart->error = new stdclass();
$lang->workflowflowchart->error->adminOnly           = 'Only administrators can modify flow definitions.';
$lang->workflowflowchart->error->denied              = 'You do not have permission to view this flowchart.';
$lang->workflowflowchart->error->notInstalled        = 'The flowchart table is not installed.';
$lang->workflowflowchart->error->invalidJSON         = 'The flow definition is not valid JSON.';
$lang->workflowflowchart->error->invalidObjectType   = 'Invalid object type.';
$lang->workflowflowchart->error->invalidNodes        = 'Invalid node list.';
$lang->workflowflowchart->error->invalidEdges        = 'Invalid edge list.';
$lang->workflowflowchart->error->invalidNode         = 'A node definition is incomplete.';
$lang->workflowflowchart->error->duplicateNode       = 'Node IDs must be unique.';
$lang->workflowflowchart->error->invalidStatus       = 'A node contains an unsupported status.';
$lang->workflowflowchart->error->invalidEdge         = 'An edge definition is incomplete.';
$lang->workflowflowchart->error->duplicateEdge       = 'Edge IDs must be unique.';
$lang->workflowflowchart->error->invalidEndpoint     = 'An edge references a missing node.';
$lang->workflowflowchart->error->invalidAction       = 'An edge contains an unsupported action.';
$lang->workflowflowchart->error->duplicateTransition = 'A source, target, and action combination must be unique.';
$lang->workflowflowchart->error->transitionDenied    = 'The workflow does not allow transition from "%s" to "%s".';
$lang->workflowflowchart->error->actorDenied         = 'The current user is not allowed by this transition rule.';
$lang->workflowflowchart->error->commentRequired     = 'This transition requires a comment.';

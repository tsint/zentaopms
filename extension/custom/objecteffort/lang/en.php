<?php
$lang->objecteffort = new stdclass();

$lang->objecteffort->common      = 'Object Effort';
$lang->objecteffort->record      = 'Record Effort';
$lang->objecteffort->edit        = 'Edit Effort';
$lang->objecteffort->delete      = 'Delete Effort';
$lang->objecteffort->effort      = 'Effort';
$lang->objecteffort->date        = 'Date';
$lang->objecteffort->account     = 'User';
$lang->objecteffort->execution   = 'Execution';
$lang->objecteffort->project     = 'Project';
$lang->objecteffort->estimate    = 'Estimate';
$lang->objecteffort->consumed    = 'Consumed';
$lang->objecteffort->left        = 'Left';
$lang->objecteffort->work        = 'Work';
$lang->objecteffort->actions     = 'Actions';
$lang->objecteffort->empty       = 'No effort records.';
$lang->objecteffort->confirmDelete = 'Delete this effort record?';

$lang->objecteffort->error = new stdclass();
$lang->objecteffort->error->objectType = 'Invalid object type.';
$lang->objecteffort->error->object     = 'Object does not exist or has been deleted.';
$lang->objecteffort->error->closed     = 'Closed objects cannot record effort.';
$lang->objecteffort->error->date       = 'Date is required and cannot be later than today.';
$lang->objecteffort->error->consumed   = 'Consumed hours must be a number greater than 0.';
$lang->objecteffort->error->left       = 'Left hours must be a number.';
$lang->objecteffort->error->leftZeroEstimate = 'Left hours cannot be negative when the estimate is 0.';
$lang->objecteffort->error->denied     = 'You do not have permission to operate this effort record.';
$lang->objecteffort->error->executionRequired = 'Select an execution linked to this Story.';
$lang->objecteffort->error->execution         = 'The selected execution is not linked to this Story.';
$lang->objecteffort->error->projectRequired   = 'Select a project linked to this Story.';
$lang->objecteffort->error->project           = 'The selected project is not linked to this Story.';

<?php
declare(strict_types=1);
/**
 * The en language file of statetransition module.
 */

$lang->statetransition = new stdClass();
$lang->statetransition->common       = 'State Transition';
$lang->statetransition->browse       = 'Workflow Configuration';
$lang->statetransition->manage       = 'Manage workflow';
$lang->statetransition->createStatus = 'Add Status';
$lang->statetransition->createTransition = 'Add Transition';
$lang->statetransition->view         = 'View Flow';
$lang->statetransition->resetDefault = 'Reset to Default';

$lang->statetransition->objectTypeList = array(
    'epic'        => 'Epic',
    'requirement' => 'Requirement',
    'story'       => 'Story',
    'bug'         => 'Bug',
    'task'        => 'Task',
);

$lang->statetransition->scope                = 'Scope';
$lang->statetransition->scopeList            = array('global' => 'Global', 'product' => 'Product');
$lang->statetransition->product              = 'Product';
$lang->statetransition->copyFromGlobal       = 'Copy from global';
$lang->statetransition->resetToDefault       = 'Reset to default';

$lang->statetransition->enabled              = 'Enable this workflow';
$lang->statetransition->enableHint           = 'When unchecked, the workflow is visualization-only and does not restrict transitions.';
$lang->statetransition->statuses             = 'Statuses';
$lang->statetransition->transitions          = 'Transitions';
$lang->statetransition->preview              = 'Preview';
$lang->statetransition->addStatus            = 'Add status';
$lang->statetransition->addTransition        = 'Add transition';
$lang->statetransition->edit                 = 'Edit';
$lang->statetransition->delete               = 'Delete';
$lang->statetransition->save                 = 'Save';
$lang->statetransition->saveAndClose         = 'Save & close';

$lang->statetransition->statusKey            = 'Key';
$lang->statetransition->statusLabel          = 'Label';
$lang->statetransition->statusCategory       = 'Category';
$lang->statetransition->statusColor          = 'Color';
$lang->statetransition->statusIsEntry        = 'Entry status';
$lang->statetransition->statusActions        = 'Actions';
$lang->statetransition->categoryList         = array('normal' => 'Normal', 'abnormal' => 'Abnormal', 'terminal' => 'Terminal');

$lang->statetransition->transitionFrom       = 'From';
$lang->statetransition->transitionTo         = 'To';
$lang->statetransition->transitionAction     = 'Action';
$lang->statetransition->transitionBranch     = 'Branch';
$lang->statetransition->reviewBranch         = 'Review result';
$lang->statetransition->transitionLabel      = 'Label';
$lang->statetransition->transitionRoles      = 'Roles';
$lang->statetransition->transitionAccounts   = 'Accounts';
$lang->statetransition->transitionRequireComment = 'Require comment';
$lang->statetransition->transitionEnabled    = 'Enabled';
$lang->statetransition->transitionIsCustom   = 'Render as custom button';
$lang->statetransition->buttonLabel          = 'Button label';
$lang->statetransition->buttonIcon           = 'Button icon';
$lang->statetransition->buttonGroup          = 'Button group';
$lang->statetransition->buttonGroupList      = array('primary' => 'Primary', 'more' => 'More', 'danger' => 'Danger');
$lang->statetransition->buttonOrder          = 'Order';
$lang->statetransition->assignedTo           = 'Assign To';

$lang->statetransition->actionList = array(
    'submitreview'   => 'Submit for review',
    'review'         => 'Review',
    'change'         => 'Change',
    'recallreview'   => 'Recall review',
    'recallchange'   => 'Recall change',
    'confirm'        => 'Confirm',
    'assignTo'       => 'Assign',
    'close'          => 'Close',
    'activate'       => 'Activate',
    'resolve'        => 'Resolve',
    'start'          => 'Start',
    'restart'        => 'Restart',
    'pause'          => 'Pause',
    'finish'         => 'Finish',
    'cancel'         => 'Cancel',
);
$lang->statetransition->branchList = array(
    'pass'    => 'Pass',
    'reject'  => 'Reject',
    'clarify' => 'Clarify',
    'revert'  => 'Revert',
    'done'    => 'Done',
    'rejected'=> 'Rejected',
);
$lang->statetransition->reviewBranchTip      = 'Review actions match workflow rules by review result branch, such as pass or reject.';

$lang->statetransition->requireCommentTip    = 'A comment is required for this action';
$lang->statetransition->customButtonTip      = 'Render as a custom button on detail pages';
$lang->statetransition->currentStatus        = 'Current';
$lang->statetransition->configureWorkflow    = 'Configure workflow';
$lang->statetransition->flowDiagram          = 'State flow diagram';

$lang->statetransition->errors = array(
    'objectTypeInvalid'    => 'Invalid object type',
    'definitionNotFound'   => 'No workflow definition (unrestricted)',
    'definitionDisabled'   => 'Workflow disabled (unrestricted)',
    'transitionNotFound'   => 'No matching transition',
    'transitionDisabled'   => 'Transition disabled',
    'ambiguousBranch'      => 'Multiple branches exist for this action; specify branch',
    'actorDenied'          => 'Actor not allowed',
    'commentRequired'      => 'Comment required for this action',
    'customStatusInvalid'  => 'Invalid custom status',
    'statusInUse'          => 'Status in use; cannot delete',
    'adminOnly'            => 'Admin only',
    'versionConflict'      => 'Definition changed by another user; refresh and retry',
    'invalidDefinition'    => 'Invalid definition JSON',
    'duplicateTransition'  => 'Duplicate transition exists',
    'statusKeyDuplicate'   => 'Duplicate status key',
    'statusKeyInvalid'     => 'Status key must start with a letter and contain only lowercase letters/digits/underscore, 2-30 chars',
    'transitionRefInvalid' => 'Transition references a non-existent status',
    'actionInvalid'        => 'Action not in whitelist',
    'branchInvalid'        => 'Invalid action branch',
    'reviewSourceInvalid'  => 'Review action can only start from the reviewing status',
    'systemStatusLocked'   => 'System status key cannot be overridden',
);

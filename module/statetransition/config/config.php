<?php
declare(strict_types=1);
/**
 * The config file of statetransition module.
 *
 * Provides:
 *  - objectTypes: the 5 object types covered (epic/requirement/story/bug/task)
 *  - actions: built-in action whitelist per objectType
 *  - systemStatuses: built-in status keys that cannot be redefined as custom
 *  - defaultDefinitions: default definition per objectType (returned by getDefaultDefinition)
 *  - branches: built-in branch vocabulary per action (used by review/close)
 */

$config->statetransition = new stdClass();

/* The object types covered by the lightweight state transition workflow.
   Epic/requirement must follow the feature switches from admin → 功能配置. */
$config->statetransition->objectTypes = array('story', 'bug', 'task');
if(!empty($config->URAndSR)) $config->statetransition->objectTypes[] = 'requirement';
if(!empty($config->enableER)) $config->statetransition->objectTypes[] = 'epic';
/* Re-order to epic → requirement → story → bug → task for consistent display. */
$objectTypeOrder = array('epic' => 1, 'requirement' => 2, 'story' => 3, 'bug' => 4, 'task' => 5);
usort($config->statetransition->objectTypes, function($a, $b) use ($objectTypeOrder) {
    return ($objectTypeOrder[$a] ?? 99) <=> ($objectTypeOrder[$b] ?? 99);
});

/* Map objectType -> business module that owns the status field. */
$config->statetransition->objectModules = array(
    'epic'       => 'story',
    'requirement'=> 'story',
    'story'      => 'story',
    'bug'        => 'bug',
    'task'       => 'task',
);

/* Built-in action whitelist per objectType. Custom buttons use the custom_* namespace and bypass this list. */
$config->statetransition->actions = array(
    'epic'        => array('submitreview', 'review', 'change', 'recallreview', 'recallchange', 'assignTo', 'resolve', 'close', 'activate'),
    'requirement' => array('submitreview', 'review', 'change', 'recallreview', 'recallchange', 'assignTo', 'resolve', 'close', 'activate'),
    'story'       => array('submitreview', 'review', 'change', 'recallreview', 'recallchange', 'assignTo', 'resolve', 'close', 'activate'),
    'bug'         => array('confirm', 'assignTo', 'resolve', 'close', 'activate'),
    'task'        => array('assignTo', 'start', 'restart', 'pause', 'finish', 'resolve', 'close', 'cancel', 'activate'),
);

/* Built-in branches per action. Null means single-branch. */
$config->statetransition->actionBranches = array(
    'submitreview'   => null,
    'review'         => array('pass', 'reject', 'clarify', 'revert'),
    'change'         => null,
    'recallreview'   => null,
    'recallchange'   => null,
    'close'          => array('done', 'rejected'),
    'confirm'        => null,
    'assignTo'       => null,
    'activate'       => null,
    'resolve'        => null,
    'start'          => null,
    'restart'        => null,
    'pause'          => null,
    'finish'         => null,
    'cancel'         => null,
);

/* System status keys per objectType. Custom statuses cannot reuse these keys. */
$config->statetransition->systemStatuses = array(
    'story'       => array('draft', 'reviewing', 'active', 'changing', 'closed'),
    'epic'        => array('draft', 'reviewing', 'active', 'changing', 'closed'),
    'requirement' => array('draft', 'reviewing', 'active', 'changing', 'closed'),
    'bug'         => array('active', 'resolved', 'closed'),
    'task'        => array('wait', 'doing', 'done', 'pause', 'cancel', 'closed'),
);

/* Schema version of freshly created definitions. */
$config->statetransition->schemaVersion = 1;

/* Status key regex (PRD §4.3). */
$config->statetransition->statusKeyPattern = '/^[a-z][a-z0-9_]{1,29}$/';

/* Color preset for custom statuses (UI picker). */
$config->statetransition->colorPresets = array('#999999', '#3498db', '#27ae60', '#f39c12', '#7f8c8d', '#e74c3c', '#9b59b6', '#1abc9c');

/* Whether the workflow guard is enabled globally. Per-definition enabled flag still applies. */
$config->statetransition->globalEnabled = true;

/* Default definitions are big arrays; load from a separate file to keep this readable. */
include __DIR__ . '/defaults.php';

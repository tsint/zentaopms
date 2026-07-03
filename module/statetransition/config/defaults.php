<?php
declare(strict_types=1);
/**
 * Default workflow definitions for each objectType.
 *
 * Used by statetransitionModel::getDefaultDefinition(). Returned as native PHP arrays;
 * saveDefinition() will json_encode them when persisting to zt_workflow_definition.definition.
 *
 * epic and requirement reuse story's definition (PRD §3.1: epic/requirement are thin wrappers over story).
 */

$config->statetransition->defaultDefinitions = array();

/* Shared story-derivation definition (used by epic/requirement/story). */
$storyDefinition = array(
    'schemaVersion' => 1,
    'statuses' => array(
        array('key' => 'draft',     'label' => array('zh_cn' => '草稿',   'en' => 'Draft'),     'category' => 'normal',   'color' => '#999999', 'isSystem' => true,  'isEntry' => true,  'fieldRules' => new stdClass()),
        array('key' => 'reviewing', 'label' => array('zh_cn' => '评审中', 'en' => 'Reviewing'), 'category' => 'normal',   'color' => '#3498db', 'isSystem' => true,  'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'active',    'label' => array('zh_cn' => '激活',   'en' => 'Active'),    'category' => 'normal',   'color' => '#27ae60', 'isSystem' => true,  'isEntry' => true,  'fieldRules' => new stdClass()),
        array('key' => 'changing',  'label' => array('zh_cn' => '变更中', 'en' => 'Changing'),  'category' => 'abnormal', 'color' => '#f39c12', 'isSystem' => true,  'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'closed',    'label' => array('zh_cn' => '已关闭', 'en' => 'Closed'),    'category' => 'terminal', 'color' => '#7f8c8d', 'isSystem' => true,  'isEntry' => false, 'fieldRules' => new stdClass()),
    ),
    'transitions' => array(
        array('key' => 'draft-to-reviewing-via-submitreview',   'fromStatus' => 'draft',     'toStatus' => 'reviewing', 'action' => 'submitreview', 'branch' => null,    'label' => array('zh_cn' => '提交评审', 'en' => 'Submit'),         'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'reviewing-to-active-via-review-pass',   'fromStatus' => 'reviewing', 'toStatus' => 'active',    'action' => 'review',       'branch' => 'pass',  'label' => array('zh_cn' => '评审通过', 'en' => 'Pass'),           'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'reviewing-to-closed-via-review-reject', 'fromStatus' => 'reviewing', 'toStatus' => 'closed',    'action' => 'review',       'branch' => 'reject','label' => array('zh_cn' => '拒绝',    'en' => 'Reject'),         'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'reviewing-to-draft-via-review-clarify', 'fromStatus' => 'reviewing', 'toStatus' => 'draft',     'action' => 'review',       'branch' => 'clarify','label' => array('zh_cn' => '需澄清',  'en' => 'Clarify'),        'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'reviewing-to-draft-via-recallreview',   'fromStatus' => 'reviewing', 'toStatus' => 'draft',     'action' => 'recallreview', 'branch' => null,    'label' => array('zh_cn' => '撤回评审', 'en' => 'Recall'),         'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'active-to-changing-via-change',         'fromStatus' => 'active',    'toStatus' => 'changing',  'action' => 'change',       'branch' => null,    'label' => array('zh_cn' => '变更',    'en' => 'Change'),         'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'changing-to-active-via-recallchange',   'fromStatus' => 'changing',  'toStatus' => 'active',    'action' => 'recallchange', 'branch' => null,    'label' => array('zh_cn' => '撤回变更', 'en' => 'Recall Change'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'active-to-closed-via-close',             'fromStatus' => 'active',    'toStatus' => 'closed',    'action' => 'close',        'branch' => null,    'label' => array('zh_cn' => '关闭',    'en' => 'Close'),          'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'closed-to-active-via-activate',         'fromStatus' => 'closed',    'toStatus' => 'active',    'action' => 'activate',     'branch' => null,    'label' => array('zh_cn' => '激活',    'en' => 'Activate'),       'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
    ),
    'entries' => array('draft', 'active'),
);

$config->statetransition->defaultDefinitions['epic']        = $storyDefinition;
$config->statetransition->defaultDefinitions['requirement'] = $storyDefinition;
$config->statetransition->defaultDefinitions['story']       = $storyDefinition;

$bugDefinition = array(
    'schemaVersion' => 1,
    'statuses' => array(
        array('key' => 'active',   'label' => array('zh_cn' => '激活',   'en' => 'Active'),   'category' => 'normal',   'color' => '#27ae60', 'isSystem' => true, 'isEntry' => true,  'fieldRules' => new stdClass()),
        array('key' => 'resolved', 'label' => array('zh_cn' => '已解决', 'en' => 'Resolved'), 'category' => 'normal',   'color' => '#3498db', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'closed',   'label' => array('zh_cn' => '已关闭', 'en' => 'Closed'),   'category' => 'terminal', 'color' => '#7f8c8d', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
    ),
    'transitions' => array(
        array('key' => 'active-to-resolved-via-resolve',    'fromStatus' => 'active',   'toStatus' => 'resolved', 'action' => 'resolve',  'branch' => null,   'label' => array('zh_cn' => '解决', 'en' => 'Resolve'),  'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'resolved-to-closed-via-close',           'fromStatus' => 'resolved', 'toStatus' => 'closed',   'action' => 'close',    'branch' => null,   'label' => array('zh_cn' => '关闭', 'en' => 'Close'),   'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'resolved-to-active-via-activate',   'fromStatus' => 'resolved', 'toStatus' => 'active',   'action' => 'activate', 'branch' => null,   'label' => array('zh_cn' => '激活', 'en' => 'Activate'),'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'closed-to-active-via-activate',     'fromStatus' => 'closed',   'toStatus' => 'active',   'action' => 'activate', 'branch' => null,   'label' => array('zh_cn' => '重开', 'en' => 'Reopen'),  'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
    ),
    'entries' => array('active'),
);
$config->statetransition->defaultDefinitions['bug'] = $bugDefinition;

$taskDefinition = array(
    'schemaVersion' => 1,
    'statuses' => array(
        array('key' => 'wait',   'label' => array('zh_cn' => '未开始', 'en' => 'Wait'),   'category' => 'normal',   'color' => '#999999', 'isSystem' => true, 'isEntry' => true,  'fieldRules' => new stdClass()),
        array('key' => 'doing',  'label' => array('zh_cn' => '进行中', 'en' => 'Doing'),  'category' => 'normal',   'color' => '#3498db', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'done',   'label' => array('zh_cn' => '已完成', 'en' => 'Done'),   'category' => 'normal',   'color' => '#27ae60', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'pause',  'label' => array('zh_cn' => '已暂停', 'en' => 'Pause'),  'category' => 'abnormal', 'color' => '#f39c12', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'cancel', 'label' => array('zh_cn' => '已取消', 'en' => 'Cancel'), 'category' => 'terminal', 'color' => '#e74c3c', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
        array('key' => 'closed', 'label' => array('zh_cn' => '已关闭', 'en' => 'Closed'), 'category' => 'terminal', 'color' => '#7f8c8d', 'isSystem' => true, 'isEntry' => false, 'fieldRules' => new stdClass()),
    ),
    'transitions' => array(
        array('key' => 'wait-to-doing-via-start',      'fromStatus' => 'wait',  'toStatus' => 'doing',  'action' => 'start',    'branch' => null, 'label' => array('zh_cn' => '开始', 'en' => 'Start'),    'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'pause-to-doing-via-restart',   'fromStatus' => 'pause', 'toStatus' => 'doing',  'action' => 'restart',  'branch' => null, 'label' => array('zh_cn' => '继续', 'en' => 'Restart'), 'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'doing-to-pause-via-pause',     'fromStatus' => 'doing', 'toStatus' => 'pause',  'action' => 'pause',    'branch' => null, 'label' => array('zh_cn' => '暂停', 'en' => 'Pause'),   'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'doing-to-done-via-finish',     'fromStatus' => 'doing', 'toStatus' => 'done',   'action' => 'finish',   'branch' => null, 'label' => array('zh_cn' => '完成', 'en' => 'Finish'),  'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'wait-to-closed-via-close',     'fromStatus' => 'wait',  'toStatus' => 'closed', 'action' => 'close',    'branch' => null, 'label' => array('zh_cn' => '关闭', 'en' => 'Close'),   'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'doing-to-closed-via-close',    'fromStatus' => 'doing', 'toStatus' => 'closed', 'action' => 'close',    'branch' => null, 'label' => array('zh_cn' => '关闭', 'en' => 'Close'),   'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'done-to-closed-via-close',     'fromStatus' => 'done',  'toStatus' => 'closed', 'action' => 'close',    'branch' => null, 'label' => array('zh_cn' => '关闭', 'en' => 'Close'),   'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'doing-to-cancel-via-cancel',   'fromStatus' => 'doing', 'toStatus' => 'cancel', 'action' => 'cancel',   'branch' => null, 'label' => array('zh_cn' => '取消', 'en' => 'Cancel'),  'roles' => array(), 'accounts' => array(), 'requireComment' => true,  'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'danger',  'sideEffects' => array(), 'condition' => null),
        array('key' => 'pause-to-doing-via-activate',  'fromStatus' => 'pause', 'toStatus' => 'doing',  'action' => 'activate', 'branch' => null, 'label' => array('zh_cn' => '激活', 'en' => 'Activate'),'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'cancel-to-doing-via-activate', 'fromStatus' => 'cancel','toStatus' => 'doing',  'action' => 'activate', 'branch' => null, 'label' => array('zh_cn' => '激活', 'en' => 'Activate'),'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
        array('key' => 'closed-to-doing-via-activate', 'fromStatus' => 'closed','toStatus' => 'doing',  'action' => 'activate', 'branch' => null, 'label' => array('zh_cn' => '激活', 'en' => 'Activate'),'roles' => array(), 'accounts' => array(), 'requireComment' => false, 'enabled' => true, 'isCustom' => false, 'buttonLabel' => null, 'buttonIcon' => null, 'buttonOrder' => 0, 'buttonGroup' => 'primary', 'sideEffects' => array(), 'condition' => null),
    ),
    'entries' => array('wait'),
);
$config->statetransition->defaultDefinitions['task'] = $taskDefinition;

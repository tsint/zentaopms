<?php
declare(strict_types=1);
/**
 * The zh-cn language file of statetransition module.
 */

$lang->statetransition = new stdClass();
$lang->statetransition->common       = '状态流转';
$lang->statetransition->browse       = '状态流转配置';
$lang->statetransition->manage       = '管理状态流转';
$lang->statetransition->createStatus = '新增状态';
$lang->statetransition->createTransition = '新增转移';
$lang->statetransition->view         = '查看流程图';
$lang->statetransition->resetDefault = '恢复默认';

/* Tabs */
$lang->statetransition->objectTypeList = array(
    'epic'        => '业务需求',
    'requirement' => '用户需求',
    'story'       => '研发需求',
    'bug'         => 'Bug',
    'task'        => '任务',
);

/* Scope */
$lang->statetransition->scope                = '作用域';
$lang->statetransition->scopeList            = array('global' => '系统默认', 'product' => '产品级');
$lang->statetransition->product              = '产品';
$lang->statetransition->copyFromGlobal       = '从系统默认复制';
$lang->statetransition->resetToDefault       = '恢复默认';

/* Form labels */
$lang->statetransition->enabled              = '启用此流程';
$lang->statetransition->enableHint           = '勾选后才会限制状态变迁；未勾选仅做可视化展示，不影响业务操作。';
$lang->statetransition->statuses             = '状态列表';
$lang->statetransition->transitions          = '转移规则';
$lang->statetransition->preview              = '流程预览';
$lang->statetransition->addStatus            = '新增状态';
$lang->statetransition->addTransition        = '新增转移';
$lang->statetransition->edit                 = '编辑';
$lang->statetransition->delete               = '删除';
$lang->statetransition->save                 = '保存';
$lang->statetransition->saveAndClose         = '保存并关闭';

$lang->statetransition->statusKey            = 'Key';
$lang->statetransition->statusLabel          = '显示名';
$lang->statetransition->statusCategory       = '类别';
$lang->statetransition->statusColor          = '颜色';
$lang->statetransition->statusIsEntry        = '允许作为创建初始状态';
$lang->statetransition->statusActions        = '操作';
$lang->statetransition->categoryList         = array('normal' => '正常', 'abnormal' => '异常', 'terminal' => '终态');

$lang->statetransition->transitionFrom       = '源状态';
$lang->statetransition->transitionTo         = '目标状态';
$lang->statetransition->transitionAction     = '动作';
$lang->statetransition->transitionBranch     = '分支';
$lang->statetransition->reviewBranch         = '评审意见';
$lang->statetransition->transitionLabel      = '显示名';
$lang->statetransition->transitionRoles      = '允许角色';
$lang->statetransition->transitionAccounts   = '允许账号';
$lang->statetransition->transitionRequireComment = '强制评论';
$lang->statetransition->transitionEnabled    = '启用';
$lang->statetransition->transitionIsCustom   = '作为自定义按钮';
$lang->statetransition->buttonLabel          = '按钮文字';
$lang->statetransition->buttonIcon           = '按钮图标';
$lang->statetransition->buttonGroup          = '按钮分组';
$lang->statetransition->buttonGroupList      = array('primary' => '主操作', 'more' => '更多', 'danger' => '危险');
$lang->statetransition->buttonOrder          = '排序';

$lang->statetransition->actionList           = array(
    'submitreview'   => '提交评审',
    'review'         => '评审',
    'change'         => '变更',
    'recallreview'   => '撤回评审',
    'recallchange'   => '撤回变更',
    'confirm'        => '确认',
    'assignTo'       => '指派',
    'close'          => '关闭',
    'activate'       => '激活',
    'resolve'        => '解决',
    'start'          => '开始',
    'restart'        => '继续',
    'pause'          => '暂停',
    'finish'         => '完成',
    'cancel'         => '取消',
);
$lang->statetransition->branchList           = array(
    'pass'    => '确认通过',
    'reject'  => '拒绝',
    'clarify' => '需澄清',
    'revert'  => '撤回',
    'done'    => '正常关闭',
    'rejected'=> '拒绝关闭',
);

$lang->statetransition->requireCommentTip    = '此操作需要填写评论';
$lang->statetransition->customButtonTip      = '作为详情页自定义按钮显示';
$lang->statetransition->currentStatus        = '当前状态';
$lang->statetransition->configureWorkflow    = '配置此工作流';
$lang->statetransition->flowDiagram          = '状态流转图';

/* Browse page (M4) */
$lang->statetransition->globalScope          = '系统默认（全局）';
$lang->statetransition->manageTitle          = '管理状态流转定义';
$lang->statetransition->confirmReset         = '确定要恢复默认定义吗？所有自定义修改将丢失。';
$lang->statetransition->reset                = '恢复默认';
$lang->statetransition->fieldObjectType      = '对象类型';
$lang->statetransition->fieldScope           = '作用域';
$lang->statetransition->fieldState           = '当前状态';
$lang->statetransition->fieldVersion         = '版本';
$lang->statetransition->fieldStatusCount     = '状态数';
$lang->statetransition->fieldTransitionCount = '转移数';
$lang->statetransition->summaryTitle         = '概要';
$lang->statetransition->stateDefault         = '默认（未自定义）';
$lang->statetransition->stateEnabled         = '已启用';
$lang->statetransition->stateDisabled        = '已禁用';
$lang->statetransition->fieldStatusKey       = '状态 Key';
$lang->statetransition->fieldStatusLabel     = '显示名';
$lang->statetransition->fieldCategory        = '类别';
$lang->statetransition->fieldColor           = '颜色';
$lang->statetransition->fieldIsSystem        = '系统';
$lang->statetransition->fieldIsEntry         = '可初始';
$lang->statetransition->fieldTransitionKey   = '转移 Key';
$lang->statetransition->fieldAction          = '动作';
$lang->statetransition->fieldBranch          = '分支';
$lang->statetransition->fieldFromStatus      = '源状态';
$lang->statetransition->fieldToStatus        = '目标状态';
$lang->statetransition->fieldRequireComment  = '强制评论';
$lang->statetransition->fieldEnabled         = '启用';
$lang->statetransition->transitionsTitle     = '转移规则';
$lang->statetransition->flowDiagramTitle     = '状态流转图';
$lang->statetransition->definitionJSON       = '定义 JSON';
$lang->statetransition->hintEditingDefault   = '当前展示的是默认定义。保存后会创建自定义定义，覆盖默认值。';

/* Visual editor (M4 v2) */
$lang->statetransition->enabledTip           = '启用后，未在流程图中配置的状态流转将被拒绝。';
$lang->statetransition->addNode              = '添加状态';
$lang->statetransition->deleteNode           = '删除状态';
$lang->statetransition->setAsEntry           = '设为初始状态';
$lang->statetransition->unsetEntry           = '取消初始状态';
$lang->statetransition->entryNodeTitle       = '初始状态：对象创建时从此状态进入流程';
$lang->statetransition->nodeID               = '状态 key，如 accepted';
$lang->statetransition->nodeLabel            = '显示名称';
$lang->statetransition->transitionRule       = '流转规则';
$lang->statetransition->source               = '起始状态';
$lang->statetransition->target               = '目标状态';
$lang->statetransition->action               = '动作';
$lang->statetransition->label                = '显示名称';
$lang->statetransition->requireComment       = '必须填写备注';
$lang->statetransition->transitionName       = '流转名称（如 不做 / 重复 / 无效）';
$lang->statetransition->reviewBranchTip      = '评审动作会按评审意见匹配流转规则，例如确认通过对应 pass，拒绝对应 reject。';
$lang->statetransition->roles                = '允许角色';
$lang->statetransition->accounts             = '允许用户';
$lang->statetransition->assignedTo           = '指派给';
$lang->statetransition->description          = '规则说明';
$lang->statetransition->edgeEnabled          = '启用此流转';
$lang->statetransition->deleteEdge           = '删除流转';
$lang->statetransition->saveWorkflow         = '保存流程';
$lang->statetransition->selectEdge           = '在上方流程图中点击一条连线，或点击下方流转卡片，开始编辑规则。';
$lang->statetransition->allActors            = '不选择角色或用户时，所有有操作权限的用户均可执行。';
$lang->statetransition->readonly             = '只读流程图';
$lang->statetransition->transitionList       = '流转列表';
$lang->statetransition->nodeMatrix           = '状态节点视图';
$lang->statetransition->ruleMatrix           = '流转规则列表';
$lang->statetransition->flowEmpty            = '暂无启用的流转规则。';
$lang->statetransition->category             = '状态类别';
$lang->statetransition->color                = '颜色';
$lang->statetransition->isEntry              = '初始状态';
$lang->statetransition->customButton         = '作为自定义按钮';
$lang->statetransition->backToBrowse         = '返回列表';

/* Error keys (PRD §5.8) */
$lang->statetransition->errors = array(
    'objectTypeInvalid'    => '对象类型无效',
    'definitionNotFound'   => '未配置工作流定义（无约束）',
    'definitionDisabled'   => '工作流未启用（无约束）',
    'transitionNotFound'   => '当前状态没有匹配的转移规则',
    'transitionDisabled'   => '该转移已被禁用',
    'ambiguousBranch'      => '同一动作存在多条分支，未指定 branch',
    'actorDenied'          => '当前用户不在允许的操作者范围',
    'commentRequired'      => '此操作必须填写评论',
    'customStatusInvalid'  => '自定义状态不合法',
    'statusInUse'          => '状态使用中，无法删除',
    'adminOnly'            => '仅管理员可保存',
    'versionConflict'      => '定义已被他人修改，请刷新后重试',
    'invalidDefinition'    => '定义 JSON 不合法',
    'duplicateTransition'  => '已存在相同的转移规则',
    'statusKeyDuplicate'   => '状态 key 重复',
    'statusKeyInvalid'     => '状态 key 必须以字母开头，仅含小写字母/数字/下划线，2-30 字符',
    'transitionRefInvalid' => '转移规则引用了不存在的状态',
    'actionInvalid'        => '动作不在白名单',
    'branchInvalid'        => '动作分支不合法',
    'reviewSourceInvalid'  => '评审动作只能从评审中状态发起',
    'systemStatusLocked'   => '系统状态 key 不可被自定义状态覆盖',
);

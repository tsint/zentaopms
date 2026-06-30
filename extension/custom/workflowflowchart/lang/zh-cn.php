<?php
$lang->workflowflowchart = new stdclass();
$lang->workflowflowchart->common         = '状态流转图';
$lang->workflowflowchart->manage         = '流程配置';
$lang->workflowflowchart->browse         = '查看流程';
$lang->workflowflowchart->enabled        = '启用流转约束';
$lang->workflowflowchart->enabledTip     = '启用后，未在流程图中配置的状态流转将被拒绝。';
$lang->workflowflowchart->addTransition  = '添加流转';
$lang->workflowflowchart->transitionRule = '流转规则';
$lang->workflowflowchart->source         = '起始状态';
$lang->workflowflowchart->target         = '目标状态';
$lang->workflowflowchart->action         = '执行动作';
$lang->workflowflowchart->label          = '显示名称';
$lang->workflowflowchart->roles          = '允许角色';
$lang->workflowflowchart->accounts       = '允许用户';
$lang->workflowflowchart->requireComment = '必须填写备注';
$lang->workflowflowchart->description    = '规则说明';
$lang->workflowflowchart->edgeEnabled    = '启用此流转';
$lang->workflowflowchart->deleteEdge     = '删除流转';
$lang->workflowflowchart->reset          = '恢复默认流程';
$lang->workflowflowchart->save           = '保存流程';
$lang->workflowflowchart->selectEdge     = '选择一条连线以编辑规则';
$lang->workflowflowchart->allActors      = '不选择角色或用户时，所有有操作权限的用户均可执行。';
$lang->workflowflowchart->readonly       = '只读流程图';
$lang->workflowflowchart->currentStatus  = '当前状态';
$lang->workflowflowchart->configure      = '配置流程';
$lang->workflowflowchart->transitionList = '流转列表';
$lang->workflowflowchart->flowEmpty      = '暂无启用的流转规则。';

$lang->workflowflowchart->objectTypeList = array('epic' => '业务需求', 'requirement' => '用户需求', 'story' => '研发需求', 'bug' => 'Bug', 'task' => '任务', 'testcase' => '用例');
$lang->workflowflowchart->actionList = array(
    'resolve' => '解决', 'close' => '关闭', 'activate' => '激活', 'submitreview' => '提交评审',
    'review' => '评审', 'change' => '变更', 'recallreview' => '撤回评审', 'recallchange' => '撤回变更',
    'start' => '开始', 'restart' => '继续', 'pause' => '暂停', 'finish' => '完成', 'cancel' => '取消',
    'edit' => '编辑', 'block' => '阻塞', 'investigate' => '研究'
);

$lang->workflowflowchart->error = new stdclass();
$lang->workflowflowchart->error->adminOnly           = '只有管理员可以修改流程定义。';
$lang->workflowflowchart->error->denied              = '没有权限查看流程图。';
$lang->workflowflowchart->error->notInstalled        = '流程图数据表尚未安装。';
$lang->workflowflowchart->error->invalidJSON         = '流程定义不是有效的 JSON。';
$lang->workflowflowchart->error->invalidObjectType   = '对象类型无效。';
$lang->workflowflowchart->error->invalidNodes        = '节点列表无效。';
$lang->workflowflowchart->error->invalidEdges        = '连线列表无效。';
$lang->workflowflowchart->error->invalidNode         = '节点定义不完整。';
$lang->workflowflowchart->error->duplicateNode       = '节点 ID 不能重复。';
$lang->workflowflowchart->error->invalidStatus       = '节点包含不支持的状态。';
$lang->workflowflowchart->error->invalidEdge         = '连线定义不完整。';
$lang->workflowflowchart->error->duplicateEdge       = '连线 ID 不能重复。';
$lang->workflowflowchart->error->invalidEndpoint     = '连线引用了不存在的节点。';
$lang->workflowflowchart->error->invalidAction       = '连线包含不支持的动作。';
$lang->workflowflowchart->error->duplicateTransition = '同一起始状态、目标状态和动作只能配置一次。';
$lang->workflowflowchart->error->transitionDenied    = '流程规则不允许从“%s”流转到“%s”。';
$lang->workflowflowchart->error->actorDenied         = '当前用户不在该流转规则允许的角色或用户范围内。';
$lang->workflowflowchart->error->commentRequired     = '该流转规则要求填写备注。';

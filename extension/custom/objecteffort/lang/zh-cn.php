<?php
$lang->objecteffort = new stdclass();

$lang->objecteffort->common      = '对象工时';
$lang->objecteffort->record      = '登记工时';
$lang->objecteffort->edit        = '编辑工时';
$lang->objecteffort->delete      = '删除工时';
$lang->objecteffort->effort      = '工时';
$lang->objecteffort->date        = '日期';
$lang->objecteffort->account     = '登记人';
$lang->objecteffort->execution   = '所属执行';
$lang->objecteffort->project     = '所属项目';
$lang->objecteffort->estimate    = '预计';
$lang->objecteffort->consumed    = '耗时';
$lang->objecteffort->left        = '剩余';
$lang->objecteffort->work        = '工作内容';
$lang->objecteffort->actions     = '操作';
$lang->objecteffort->empty       = '暂无工时记录';
$lang->objecteffort->confirmDelete = '确认删除该工时记录？';

$lang->objecteffort->error = new stdclass();
$lang->objecteffort->error->objectType = '对象类型无效。';
$lang->objecteffort->error->object     = '对象不存在或已删除。';
$lang->objecteffort->error->closed     = '已关闭对象不能登记工时。';
$lang->objecteffort->error->date       = '日期不能为空，且不能晚于今天。';
$lang->objecteffort->error->consumed   = '耗时必须为大于 0 的数字。';
$lang->objecteffort->error->left       = '剩余必须为不小于 0 的数字。';
$lang->objecteffort->error->denied     = '没有权限操作该工时记录。';
$lang->objecteffort->error->executionRequired = '请选择需求所属的执行。';
$lang->objecteffort->error->execution         = '选择的执行未关联当前需求。';
$lang->objecteffort->error->projectRequired   = '请选择需求所属的项目。';
$lang->objecteffort->error->project           = '选择的项目未关联当前需求。';

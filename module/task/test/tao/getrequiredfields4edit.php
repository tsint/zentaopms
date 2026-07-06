#!/usr/bin/env php
<?php
/**
title=测试 taskTao::getRequiredFields4Edit();
timeout=0
cid=18883

- 步骤1：正常任务且执行阶段有故事需求 @execution,name,type

- 步骤2：运维类型执行阶段（无需求） @execution,name,type

- 步骤3：doing状态任务且left为空检查错误属性left @任务状态为进行中时，预计剩余不能为0
- 步骤4：pause状态任务且left为空检查错误属性left @任务状态为已暂停时，预计剩余不能为0
- 步骤5：wait状态任务不受left限制 @execution,name,type
*/

// 1. 导入依赖（路径固定，不可修改）
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/tao.class.php';

// 2. zendata数据准备（根据需要配置）
$task = zenData('task');
$task->id->range('1-10');
$task->execution->range('1-5');
$task->name->range('测试任务{1-10}');
$task->type->range('devel,test,design,study,discuss');
$task->status->range('wait,doing,pause,done,cancel');
$task->left->range('0{2},1-10{8}');
$task->gen(10);

$project = zenData('project');
$project->id->range('1-5');
$project->name->range('执行阶段{1-5}');
$project->type->range('sprint,stage,kanban');
$project->lifetime->range('dev{3},ops{2}');
$project->gen(5);

// 3. 用户登录（选择合适角色）
su('admin');

// 4. 创建测试实例（变量名与模块名一致）
$taskTest = new taskTaoTest();

// 5. 🔴 强制要求：必须包含至少5个测试步骤
$taskObj1 = new stdclass();
$taskObj1->id = 1;
$taskObj1->execution = 1;
$taskObj1->status = 'wait';
$taskObj1->left = 5;
r($taskTest->getRequiredFields4EditTest($taskObj1)) && p() && e('execution,name,type'); // 步骤1：正常任务且执行阶段有故事需求

$taskObj2 = new stdclass();
$taskObj2->id = 2;
$taskObj2->execution = 4;
$taskObj2->status = 'wait';
$taskObj2->left = 5;
r($taskTest->getRequiredFields4EditTest($taskObj2)) && p() && e('execution,name,type'); // 步骤2：运维类型执行阶段（无需求）

$taskObj3 = new stdclass();
$taskObj3->id = 3;
$taskObj3->execution = 1;
$taskObj3->status = 'doing';
$taskObj3->left = 0;
r($taskTest->getRequiredFields4EditTest($taskObj3)) && p('left') && e('任务状态为进行中时，预计剩余不能为0'); // 步骤3：doing状态任务且left为空检查错误

$taskObj4 = new stdclass();
$taskObj4->id = 4;
$taskObj4->execution = 1;
$taskObj4->status = 'pause';
$taskObj4->left = 0;
r($taskTest->getRequiredFields4EditTest($taskObj4)) && p('left') && e('任务状态为已暂停时，预计剩余不能为0'); // 步骤4：pause状态任务且left为空检查错误

$taskObj5 = new stdclass();
$taskObj5->id = 5;
$taskObj5->execution = 1;
$taskObj5->status = 'wait';
$taskObj5->left = 0;
r($taskTest->getRequiredFields4EditTest($taskObj5)) && p() && e('execution,name,type'); // 步骤5：wait状态任务不受left限制
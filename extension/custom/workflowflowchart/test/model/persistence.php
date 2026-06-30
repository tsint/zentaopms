#!/usr/bin/env php
<?php
/**
title=测试 workflowflowchartModel 流程定义持久化;
cid=0

- 保存流程定义成功 @1
- 保存后启用状态正确 @1
- 保存后节点数量正确 @3
- 保存后版本号递增 @1
- 事务回滚后不污染原定义 @1
- 非管理员不能保存流程定义 @1

*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

su('admin');
global $tester, $app;
$model = $tester->loadModel('workflowflowchart');

$before = $model->dao->select('*')->from(TABLE_WORKFLOWFLOWCHART)->where('objectType')->eq('bug')->fetch();
$model->dao->begin();
$definition = $model->getDefaultDefinition('bug');
$definition['enabled'] = true;
$result = $model->saveDefinition('bug', $definition);
$stored = $model->getDefinition('bug');
$model->dao->rollback();
$after = $model->dao->select('*')->from(TABLE_WORKFLOWFLOWCHART)->where('objectType')->eq('bug')->fetch();

r($result) && p() && e(1);
r($stored['enabled']) && p() && e(1);
r(count($stored['nodes'])) && p() && e(3);
r($stored['version'] === ($before ? (int)$before->version + 1 : 1)) && p() && e(1);
r(($before ? $before->definition : '') === ($after ? $after->definition : '')) && p() && e(1);

$app->user->admin = false;
dao::$errors = array();
$denied = !$model->saveDefinition('bug', $definition);
$app->user->admin = true;
r($denied) && p() && e(1);

#!/usr/bin/env php
<?php
/**
title=测试状态流转图遵循需求功能开关;
timeout=0
cid=workflowflowchart

- 关闭业务需求和用户需求后只保留四类对象 @story,bug,task,testcase
- 仅开启用户需求后显示用户需求但隐藏业务需求 @requirement,story,bug,task,testcase
- 同时开启后显示全部六类对象 @epic,requirement,story,bug,task,testcase
- 关闭用户需求后不渲染其详情状态图 @1

*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

$model = $tester->loadModel('workflowflowchart');
$oldER = $model->config->enableER;
$oldUR = $model->config->URAndSR;

$model->config->enableER = 0;
$model->config->URAndSR  = 0;
r(implode(',', $model->getAvailableObjectTypes())) && p() && e('story,bug,task,testcase');

$model->config->enableER = 0;
$model->config->URAndSR  = 1;
r(implode(',', $model->getAvailableObjectTypes())) && p() && e('requirement,story,bug,task,testcase');

$model->config->enableER = 1;
$model->config->URAndSR  = 1;
r(implode(',', $model->getAvailableObjectTypes())) && p() && e('epic,requirement,story,bug,task,testcase');

$model->config->URAndSR = 0;
r($model->renderFlowHtml('requirement', 'draft', false) === '') && p() && e('1');

$model->config->enableER = $oldER;
$model->config->URAndSR  = $oldUR;

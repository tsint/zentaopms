#!/usr/bin/env php
<?php
/**
title=测试状态流转图遵循需求功能开关;
timeout=0
cid=0

- 执行model模块的getAvailableObjectTypes方法  @story,bug,task,testcase

- 执行model模块的getAvailableObjectTypes方法  @requirement,story,bug,task,testcase

- 执行model模块的getAvailableObjectTypes方法  @epic,requirement,story,bug,task,testcase

- 执行model模块的renderFlowHtml方法，参数是'requirement', 'draft', false) ===   @1
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

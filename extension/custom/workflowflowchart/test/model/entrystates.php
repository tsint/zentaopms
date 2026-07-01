#!/usr/bin/env php
<?php
/**
title=测试工作流状态机唯一初始状态;
timeout=0
cid=0

- 执行$singleEntry @1
- 执行model模块的validateDefinition方法，参数是'story', $invalidDefinition) === 'missingEntryState  @1
*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

$model = $tester->loadModel('workflowflowchart');
$expected = array(
    'epic'        => 'draft',
    'requirement' => 'draft',
    'story'       => 'draft',
    'bug'         => 'active',
    'task'        => 'wait',
    'testcase'    => 'normal'
);

$singleEntry = true;
foreach($expected as $objectType => $status)
{
    $source = $model->renderMermaid($objectType, $model->getDefaultDefinition($objectType));
    $valid  = substr_count($source, '[*] -->') === 1 && strpos($source, '[*] --> ' . $status) !== false;
    r($valid) && p() && e('1');
    $singleEntry = $singleEntry && $valid;
}
r($singleEntry) && p() && e('1');

$invalidDefinition = $model->getDefaultDefinition('story');
$invalidDefinition['nodes'] = array_values(array_filter($invalidDefinition['nodes'], function($node){return $node['status'] != 'draft';}));
$invalidDefinition['edges'] = array_values(array_filter($invalidDefinition['edges'], function($edge){return $edge['source'] != 'draft' && $edge['target'] != 'draft';}));
r($model->validateDefinition('story', $invalidDefinition) === 'missingEntryState') && p() && e('1');
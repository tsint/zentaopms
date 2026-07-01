#!/usr/bin/env php
<?php
/**
title=测试工作流状态机唯一初始状态;
timeout=0
cid=workflowflowchart

- 业务需求只能从草稿进入状态机 @1
- 用户需求只能从草稿进入状态机 @1
- 研发需求只能从草稿进入状态机 @1
- Bug只能从激活进入状态机 @1
- 任务只能从未开始进入状态机 @1
- 用例只能从正常进入状态机 @1
- 六类对象的Mermaid图都只有一条初始连线 @1
- 缺少真实初始节点的自定义流程不能保存 @1

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

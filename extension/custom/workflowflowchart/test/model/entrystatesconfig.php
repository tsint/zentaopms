#!/usr/bin/env php
<?php
/**
title=测试工作流状态机入口节点可配置;
timeout=0
cid=0

- 执行$defaultDefinition['entries'] @draft
- 执行$normalized['entries'] @reviewing,active

- 执行model模块的validateDefinition方法，参数是'story', $customDefinition) === true  @1
- 执行$mermaidSource, '[*] --> reviewing') !== false && strpos($mermaidSource, '[*] --> active') !== false && strpos($mermaidSource, '[*] --> draft') === false ? 1 : 0 @1
- 执行$fallbackMermaid, '[*] --> draft') !== false ? 'draft' :  @draft
- 执行model模块的validateDefinition方法，参数是'story', $invalidDefinition  @missingEntryState
- 执行$multiMermaid, '[*] --> ') === 2 && strpos($multiMermaid, '[*] --> draft') !== false && strpos($multiMermaid, '[*] --> active') !== false ? 1 : 0 @1
- 执行$normalizedEmpty['entries'] @draft
*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

global $tester;
$model = $tester->loadModel('workflowflowchart');

$defaultDefinition = $model->getDefaultDefinition('story');
r(implode(',', $defaultDefinition['entries'])) && p() && e('draft');

$customDefinition = $model->getDefaultDefinition('story');
$customDefinition['entries'] = array('reviewing', 'active');
$normalized = $model->normalizeDefinition('story', $customDefinition);
r(implode(',', $normalized['entries'])) && p() && e('reviewing,active');

r($model->validateDefinition('story', $customDefinition) === true) && p() && e(1);

$mermaidSource = $model->renderMermaid('story', $customDefinition);
r(strpos($mermaidSource, '[*] --> reviewing') !== false && strpos($mermaidSource, '[*] --> active') !== false && strpos($mermaidSource, '[*] --> draft') === false ? 1 : 0) && p() && e(1);

$noEntriesDefinition = $model->getDefaultDefinition('story');
unset($noEntriesDefinition['entries']);
$fallbackMermaid = $model->renderMermaid('story', $noEntriesDefinition);
r(strpos($fallbackMermaid, '[*] --> draft') !== false ? 'draft' : '') && p() && e('draft');

$invalidDefinition = $model->getDefaultDefinition('story');
$invalidDefinition['entries'] = array('nonexistent_status');
r($model->validateDefinition('story', $invalidDefinition)) && p() && e('missingEntryState');

$multiEntryDefinition = $model->getDefaultDefinition('story');
$multiEntryDefinition['entries'] = array('draft', 'active');
$multiMermaid = $model->renderMermaid('story', $multiEntryDefinition);
r(substr_count($multiMermaid, '[*] --> ') === 2 && strpos($multiMermaid, '[*] --> draft') !== false && strpos($multiMermaid, '[*] --> active') !== false ? 1 : 0) && p() && e(1);

$emptyEntriesDefinition = $model->getDefaultDefinition('story');
$emptyEntriesDefinition['entries'] = array();
$normalizedEmpty = $model->normalizeDefinition('story', $emptyEntriesDefinition);
r(implode(',', $normalizedEmpty['entries'])) && p() && e('draft');

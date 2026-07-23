#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->renderMermaid();
timeout=0
cid=0

- 执行$basic, 'stateDiagram-v2') !== false ? '1' : '0 @1
- 执行$basic, '[*] --> draft') !== false ? '1' : '0 @1
- 执行$basic, 'state "草稿" as draft') !== false ? '1' : '0 @1
- 执行$basic, '提交评审') !== false ? '1' : '0 @1
- 执行$basic, 'submitreview') !== false ? '1' : '0 @0
- 执行$highlight, 'classDef current') !== false ? '1' : '0 @1
- 执行$noHigh, 'classDef current') !== false ? '1' : '0 @0
- 执行$bugMermaid, 'active --> active') !== false ? '1' : '0 @0
- 执行$bugMermaid, '确认') !== false ? '1' : '0 @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$storyDef = $tester->statetransition->getDefaultDefinition('story');

$basic     = $tester->statetransition->renderMermaid($storyDef);
$highlight = $tester->statetransition->renderMermaid($storyDef, 'reviewing');
$noHigh    = $tester->statetransition->renderMermaid($storyDef, '');
$bugDef    = $tester->statetransition->getDefaultDefinition('bug');
$bugMermaid = $tester->statetransition->renderMermaid($bugDef);

r(strpos($basic, 'stateDiagram-v2') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, '[*] --> draft') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, 'state "草稿" as draft') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, '提交评审') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, 'submitreview') !== false ? '1' : '0') && p() && e('0');
r(strpos($highlight, 'classDef current') !== false ? '1' : '0') && p() && e('1');
r(strpos($noHigh, 'classDef current') !== false ? '1' : '0') && p() && e('0');
r(strpos($bugMermaid, 'active --> active') !== false ? '1' : '0') && p() && e('0');
r(strpos($bugMermaid, '确认') !== false ? '1' : '0') && p() && e('0');

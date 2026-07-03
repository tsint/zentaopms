#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->renderMermaid();
timeout=0
cid=0

- 执行$basic, 'stateDiagram-v2') !== false ? '1' : '0 @1
- 执行$basic, '[*] --> draft') !== false ? '1' : '0 @1
- 执行$basic, 'submitreview') !== false ? '1' : '0 @1
- 执行$highlight, 'classDef current') !== false ? '1' : '0 @1
- 执行$noHigh, 'classDef current') !== false ? '1' : '0 @0
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

r(strpos($basic, 'stateDiagram-v2') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, '[*] --> draft') !== false ? '1' : '0') && p() && e('1');
r(strpos($basic, 'submitreview') !== false ? '1' : '0') && p() && e('1');
r(strpos($highlight, 'classDef current') !== false ? '1' : '0') && p() && e('1');
r(strpos($noHigh, 'classDef current') !== false ? '1' : '0') && p() && e('0');
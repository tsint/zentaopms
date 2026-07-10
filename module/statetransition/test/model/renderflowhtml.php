#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->renderFlowHtml();
timeout=0
cid=0

- 执行$html) ? '1' : '0 @1
- 执行$html, 'data-mermaid-source') !== false ? '1' : '0 @1
- 执行$html, "class='mermaid'") !== false ? '1' : '0 @0
- 执行$html, 'class="mermaid"') !== false ? '1' : '0 @0
- 执行$html, 'statetransition-detail') !== false ? '1' : '0 @1
- 执行$html, '评审中') !== false ? '1' : '0 @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$storyDef = $tester->statetransition->getDefaultDefinition('story');
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

$modelTester = new statetransitionModelTest();
$html = $modelTester->renderFlowHtmlTest('story', 0, 'reviewing');

r(!empty($html) ? '1' : '0') && p() && e('1');
r(strpos($html, 'data-mermaid-source') !== false ? '1' : '0') && p() && e('1');
r(strpos($html, "class='mermaid'") !== false ? '1' : '0') && p() && e('0');
r(strpos($html, 'class="mermaid"') !== false ? '1' : '0') && p() && e('0');
r(strpos($html, 'statetransition-detail') !== false ? '1' : '0') && p() && e('1');
r(strpos($html, '评审中') !== false ? '1' : '0') && p() && e('1');

#!/usr/bin/env php
<?php
/**
title=测试 workflowflowchartModel 流程定义和流转规则;
timeout=0
cid=0

- 执行$result === true @1
- 执行$result @duplicateNode
- 执行$result @invalidAction
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'closed', 'publish', 'dev', 'user1', ''  @1
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'resolved', 'resolve', 'dev', 'user1', 'fixed'  @1
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'closed', 'activate', 'dev', 'user1', ''  @transitionDenied
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'resolved', 'resolve', 'qa', 'user1', 'fixed'  @1
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'resolved', 'resolve', 'dev', 'user1', 'fixed'  @actorDenied
- 执行model模块的checkDefinitionTransition方法，参数是$definition, 'active', 'resolved', 'resolve', 'dev', 'user1', ''  @commentRequired
- 执行model模块的isDefinitionActionAllowed方法，参数是$definition, 'active', 'resolve', 'dev', 'user1'  @1
- 执行model模块的isDefinitionActionAllowed方法，参数是$definition, 'active', 'activate', 'dev', 'user1'  @0
- 执行$result === true @1
- 执行$taskRoutes['wait>doing:start']) && isset($taskRoutes['doing>done:finish']) && isset($taskRoutes['doing>pause:pause']) && isset($taskRoutes['pause>doing:restart']) && isset($taskRoutes['done>closed:close']) && isset($taskRoutes['cancel>closed:close'] @1
- 执行$html, 'workflowflowchart-mermaid') !== false && strpos($html, 'stateDiagram-v2') !== false && strpos($html, 'data-current-status="doing"') !== false && strpos($html, 'querySelectorAll(".workflowflowchart-mermaid pre.mermaid")') !== false && strpos($html, 'document.currentScript') === false && strpos($html, 'workflowflowchart-board') === false && strpos($html, 'workflowflowchart-list') === false @1
- 执行$allDetailFlowsVisible @1
- 执行$allTypesSupported @1
- 执行$groupedHtml, 'workflowflowchart-mermaid') !== false && strpos($groupedHtml, 'workflowflowchart-row') === false && strpos($groupedHtml, 'workflowflowchart-item') === false @1
- 执行$allMermaidSupported @1
- 执行model模块的validateDefinition方法，参数是'story', $customDefinition) === true && strpos  @1
- 执行model模块的validateDefinition方法，参数是'story', $customDefinition  @invalidStatus
- 执行model模块的validateDefinition方法，参数是'bug', $namedDefinition) === true && strpos  @1
*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

global $tester;
$model = $tester->loadModel('workflowflowchart');

$definition = $model->getDefaultDefinition('bug');
$result = $model->validateDefinition('bug', $definition);
r($result === true) && p() && e(1);

$invalid = $definition;
$invalid['nodes'][] = $invalid['nodes'][0];
$result = $model->validateDefinition('bug', $invalid);
r($result) && p() && e('duplicateNode');

$invalid = $definition;
$invalid['edges'][0]['action'] = 'publish';
$result = $model->validateDefinition('bug', $invalid);
r($result) && p() && e('invalidAction');

$definition['enabled'] = false;
r($model->checkDefinitionTransition($definition, 'active', 'closed', 'publish', 'dev', 'user1', '')) && p() && e(1);

$definition['enabled'] = true;
r($model->checkDefinitionTransition($definition, 'active', 'resolved', 'resolve', 'dev', 'user1', 'fixed')) && p() && e(1);
r($model->checkDefinitionTransition($definition, 'active', 'closed', 'activate', 'dev', 'user1', '')) && p() && e('transitionDenied');

$definition['edges'][0]['roles'] = array('qa');
r($model->checkDefinitionTransition($definition, 'active', 'resolved', 'resolve', 'qa', 'user1', 'fixed')) && p() && e(1);
r($model->checkDefinitionTransition($definition, 'active', 'resolved', 'resolve', 'dev', 'user1', 'fixed')) && p() && e('actorDenied');

$definition['edges'][0]['roles'] = array();
$definition['edges'][0]['requireComment'] = true;
r($model->checkDefinitionTransition($definition, 'active', 'resolved', 'resolve', 'dev', 'user1', '')) && p() && e('commentRequired');

$definition['edges'][0]['requireComment'] = false;
r($model->isDefinitionActionAllowed($definition, 'active', 'resolve', 'dev', 'user1')) && p() && e(1);
r($model->isDefinitionActionAllowed($definition, 'active', 'activate', 'dev', 'user1')) && p() && e(0);

$taskDefinition = $model->getDefaultDefinition('task');
$result = $model->validateDefinition('task', $taskDefinition);
r($result === true) && p() && e(1);

$taskRoutes = array();
foreach($taskDefinition['edges'] as $edge) $taskRoutes[$edge['source'] . '>' . $edge['target'] . ':' . $edge['action']] = true;
r(isset($taskRoutes['wait>doing:start']) && isset($taskRoutes['doing>done:finish']) && isset($taskRoutes['doing>pause:pause']) && isset($taskRoutes['pause>doing:restart']) && isset($taskRoutes['done>closed:close']) && isset($taskRoutes['cancel>closed:close'])) && p() && e(1);

$html = $model->renderFlowHtml('task', 'doing', false);
r(strpos($html, 'workflowflowchart-mermaid') !== false && strpos($html, 'stateDiagram-v2') !== false && strpos($html, 'data-current-status="doing"') !== false && strpos($html, 'querySelectorAll(".workflowflowchart-mermaid pre.mermaid")') !== false && strpos($html, 'document.currentScript') === false && strpos($html, 'workflowflowchart-board') === false && strpos($html, 'workflowflowchart-list') === false) && p() && e(1);

$detailTypes = array('epic' => 'active', 'story' => 'active', 'requirement' => 'active', 'bug' => 'active', 'task' => 'doing');
$allDetailFlowsVisible = true;
foreach($detailTypes as $detailType => $status)
{
    $detailHtml = $model->renderFlowHtml($detailType, $status, false);
    $allDetailFlowsVisible = $allDetailFlowsVisible
        && strpos($detailHtml, 'workflowflowchart-detail') !== false
        && strpos($detailHtml, 'workflowflowchart-mermaid') !== false
        && strpos($detailHtml, 'stateDiagram-v2') !== false
        && substr_count($detailHtml, 'class="mermaid"') === 1
        && strpos($detailHtml, 'workflowflowchart-board') === false
        && strpos($detailHtml, 'workflowflowchart-list') === false;
}
r($allDetailFlowsVisible) && p() && e(1);

$objectTypes = $model->config->workflowflowchart->objectTypes;
$allTypesSupported = in_array('epic', $objectTypes, true)
    && in_array('requirement', $objectTypes, true)
    && in_array('story', $objectTypes, true)
    && in_array('bug', $objectTypes, true)
    && in_array('task', $objectTypes, true)
    && $model->validateDefinition('epic', $model->getDefaultDefinition('epic')) === true;
r($allTypesSupported) && p() && e(1);

$groupedHtml = $model->renderFlowHtml('task', 'doing', false);
r(strpos($groupedHtml, 'workflowflowchart-mermaid') !== false && strpos($groupedHtml, 'workflowflowchart-row') === false && strpos($groupedHtml, 'workflowflowchart-item') === false) && p() && e(1);

$taskMermaid = $model->renderMermaid('task', $taskDefinition);
r(strpos($taskMermaid, 'stateDiagram-v2') !== false
    && strpos($taskMermaid, '[*] --> wait') !== false
    && strpos($taskMermaid, 'wait --> doing: 开始') !== false
    && strpos($taskMermaid, 'doing --> pause: 暂停') !== false
    && strpos($taskMermaid, 'doing --> done: 完成') !== false
    && strpos($taskMermaid, 'cancel --> closed: 关闭') !== false) && p() && e(1);

$caseDefinition = $model->getDefaultDefinition('testcase');
$caseMermaid    = $model->renderMermaid('testcase', $caseDefinition);
r($model->validateDefinition('testcase', $caseDefinition) === true
    && strpos($caseMermaid, '[*] --> normal') !== false
    && strpos($caseMermaid, '[*] --> normal') !== false
    && strpos($caseMermaid, 'wait --> normal: 评审') !== false
    && strpos($caseMermaid, 'normal --> blocked: 阻塞') !== false
    && strpos($caseMermaid, 'normal --> investigate: 研究') !== false) && p() && e(1);

$objectTypes = $model->config->workflowflowchart->objectTypes;
$allMermaidSupported = count($objectTypes) === 6 && in_array('testcase', $objectTypes, true);
foreach($objectTypes as $type)
{
    $mermaid = $model->renderMermaid($type, $model->getDefaultDefinition($type));
    $allMermaidSupported = $allMermaidSupported
        && $model->validateDefinition($type, $model->getDefaultDefinition($type)) === true
        && strpos($mermaid, 'stateDiagram-v2') !== false
        && strpos($mermaid, '-->') !== false;
}
r($allMermaidSupported) && p() && e(1);

$customDefinition = $model->getDefaultDefinition('story');
$customDefinition['nodes'][] = array('id' => 'accepted', 'status' => 'accepted', 'label' => '已验收', 'x' => 0, 'y' => 0);
$customMermaid = $model->renderMermaid('story', $customDefinition);
r($model->validateDefinition('story', $customDefinition) === true && strpos($customMermaid, 'state "已验收" as accepted') !== false) && p() && e(1);

$customDefinition['nodes'][] = array('id' => 'Bad Status', 'status' => 'Bad Status', 'label' => '非法', 'x' => 0, 'y' => 0);
r($model->validateDefinition('story', $customDefinition)) && p() && e('invalidStatus');

$namedDefinition = $model->getDefaultDefinition('bug');
$namedDefinition['edges'][0]['label'] = '不做 / 重复 / 无效';
$namedDefinition = $model->normalizeDefinition('bug', $namedDefinition);
$namedMermaid = $model->renderMermaid('bug', $namedDefinition);
r($model->validateDefinition('bug', $namedDefinition) === true && strpos($namedMermaid, ': 不做 / 重复 / 无效') !== false) && p() && e(1);

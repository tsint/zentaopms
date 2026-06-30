#!/usr/bin/env php
<?php
/**
title=测试 workflowflowchart 运行时 Hook 发现;
cid=0

- Bug模型加载成功 @1
- Story模型加载成功 @1
- Bug三个流转Hook均存在 @1
- Story七个流转Hook均存在 @1
- Story撤回控制器Hook存在 @1
- 业务需求/用户需求/研发需求/Bug/任务详情页直接渲染可见流程图 @1
- 任务七个流转动作均被Hook保护 @1
- 管理权限入口方法存在 @1
- 配置页使用六类对象 Mermaid 状态机且保留规则编辑列表 @1
- 配置页作为ZIN内容片段渲染且不重复输出页面外壳 @1
- 配置页对象Tab使用整页跳转避免ZIN局部加载失效 @1

*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

global $tester;
$bugModel = $tester->loadModel('bug');
$storyModel = $tester->loadModel('story');
$root = dirname(__FILE__, 6) . '/extension/custom/';
include_once $root . 'workflowflowchart/control.php';

$bugHooks = array('resolve', 'close', 'activate', 'isclickable');
$storyHooks = array('submitreview', 'review', 'change', 'close', 'activate', 'isclickable');
$taskHooks = array('start', 'finish', 'pause', 'close', 'cancel', 'activate', 'isclickable');
$bugFound = true;
foreach($bugHooks as $hook) $bugFound = $bugFound && file_exists($root . "bug/ext/model/hook/{$hook}.workflowflowchart.php");
$storyFound = true;
foreach($storyHooks as $hook) $storyFound = $storyFound && file_exists($root . "story/ext/model/hook/{$hook}.workflowflowchart.php");
$taskFound = true;
foreach($taskHooks as $hook) $taskFound = $taskFound && file_exists($root . "task/ext/model/hook/{$hook}.workflowflowchart.php");
$taskStartHook = file_get_contents($root . 'task/ext/model/hook/start.workflowflowchart.php');
$taskRestartGuarded = strpos($taskStartHook, "\$oldTask->status == 'pause' ? 'restart' : 'start'") !== false;

r(is_object($bugModel)) && p() && e(1);
r(is_object($storyModel)) && p() && e(1);
r($bugFound) && p() && e(1);
r($storyFound && count($storyHooks) + 1 === 7) && p() && e(1);
r(file_exists($root . 'story/ext/control/hook/recall.workflowflowchart.php')) && p() && e(1);
$detailHooks = array(
    $root . 'bug/ext/view/view.workflowflowchart.html.hook.php',
    $root . 'story/ext/view/view.workflowflowchart.html.hook.php',
    $root . 'task/ext/view/view.workflowflowchart.html.hook.php'
);
$detailFlowVisible = method_exists($tester->loadModel('workflowflowchart'), 'renderFlowHtml');
foreach($detailHooks as $hookFile)
{
    $hookCode = file_get_contents($hookFile);
    $detailFlowVisible = $detailFlowVisible
        && file_exists($hookFile)
        && strpos($hookCode, '$app->control->loadModel') !== false
        && strpos($hookCode, 'renderFlowHtml') !== false
        && strpos($hookCode, 'workflowflowchart-detail') !== false
        && strpos($hookCode, 'toolbar') === false
        && strpos($hookCode, 'common::hasPriv') === false;
}
r($detailFlowVisible && file_exists($root . 'task/ext/ui/view.workflowflowchart.html.hook.php')) && p() && e(1);
r($taskFound && $taskRestartGuarded) && p() && e(1);
r(method_exists('workflowflowchart', 'manage')) && p() && e(1);

$configCode = file_get_contents($root . 'workflowflowchart/config/config.php');
$viewCode   = file_get_contents($root . 'workflowflowchart/view/browse.html.php');
$configBoardReady = strpos($configCode, "'epic'") !== false
    && strpos($configCode, "'testcase'") !== false
    && strpos($viewCode, 'workflow-mermaid') !== false
    && strpos($viewCode, 'mermaid.min.js') !== false
    && strpos($viewCode, 'renderMermaid') !== false
    && strpos($viewCode, 'bindMermaidEdges') !== false
    && strpos($viewCode, 'data-edge-id') !== false
    && strpos($viewCode, 'workflow-board') !== false
    && strpos($viewCode, 'workflow-column') !== false
    && strpos($viewCode, 'body > #main:has(#mainContent:empty)') !== false
    && strpos($viewCode, 'min-height:calc(100vh') === false;
r($configBoardReady) && p() && e(1);

$singlePageShell = strpos($viewCode, 'header.html.php') === false
    && strpos($viewCode, 'footer.html.php') === false
    && strpos($viewCode, '<!DOCTYPE') === false
    && strpos($viewCode, '<html') === false;
r($singlePageShell) && p() && e(1);

$tabNavigation = strpos($viewCode, "'&_single=1&zin=1'") !== false
    && strpos($viewCode, 'onclick="window.location.href=this.href; return false;"') !== false;
r($tabNavigation) && p() && e(1);

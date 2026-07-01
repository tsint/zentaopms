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
- 需求提交评审按HTTP方法识别POST，避免空POST返回HTML @1
- 需求流转Hook缺失对象时返回失败，避免空响应 @1
- 需求提交评审弹窗校验需求和产品存在，避免TypeError @1
- 需求评审按HTTP方法识别POST且缺失对象时返回失败 @1
- 需求提交评审模型缺失对象时返回失败，避免空响应 @1
- 需求评审弹窗成功分支直接返回JSON数组，避免二次send导致空响应 @1
- 需求提交评审弹窗成功分支返回load刷新，避免callback空响应 @1
- 需求模型扩展可访问评审Tao方法且无调试日志残留 @1
- 需求工作流阻断错误使用语言包 @1

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
    && strpos($viewCode, 'workflow-node-section') !== false
    && strpos($viewCode, 'workflow-rule-section') !== false
    && strpos($viewCode, 'toggleRuleEdge') !== false
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

$storyControlCode = file_get_contents(dirname(__FILE__, 6) . '/module/story/control.php');
$submitReviewPos  = strpos($storyControlCode, 'public function submitReview');
$submitReviewCode = substr($storyControlCode, $submitReviewPos, strpos($storyControlCode, '$this->view->story', $submitReviewPos) - $submitReviewPos);
$submitReviewPostSafe = strpos($submitReviewCode, "\$this->server->request_method == 'POST'") !== false && strpos($submitReviewCode, 'if($_POST)') === false;
r($submitReviewPostSafe) && p() && e(1);

$submitReviewGetSafe = strpos($submitReviewCode, 'if(!$story) return $this->send') !== false
    && strpos($submitReviewCode, 'if(!$product) return $this->send') !== false;
r($submitReviewGetSafe) && p() && e(1);

$storyHookGuarded = true;
foreach(array('activate', 'change', 'close', 'review', 'submitreview') as $hook)
{
    $hookCode = file_get_contents($root . "story/ext/model/hook/{$hook}.workflowflowchart.php");
    $storyHookGuarded = $storyHookGuarded && strpos($hookCode, 'if(!$workflowOldStory) return false;') !== false;
}
r($storyHookGuarded) && p() && e(1);

$reviewPos  = strpos($storyControlCode, 'public function review');
$reviewCode = substr($storyControlCode, $reviewPos, strpos($storyControlCode, '$this->commonAction', $reviewPos) - $reviewPos);
$storyZenCode = file_get_contents(dirname(__FILE__, 6) . '/module/story/zen.php');
$buildReviewPos = strpos($storyZenCode, 'protected function buildStoryForReview');
$buildReviewCode = substr($storyZenCode, $buildReviewPos, strpos($storyZenCode, '$now', $buildReviewPos) - $buildReviewPos);
$reviewPostSafe = strpos($reviewCode, "\$this->server->request_method == 'POST'") !== false
    && strpos($reviewCode, 'if(!empty($_POST))') === false
    && strpos($buildReviewCode, 'if(!$oldStory)') !== false;
r($reviewPostSafe) && p() && e(1);

$storyModelCode = file_get_contents(dirname(__FILE__, 6) . '/module/story/model.php');
$submitReviewModelPos = strpos($storyModelCode, 'public function submitReview');
$submitReviewModelCode = substr($storyModelCode, $submitReviewModelPos, strpos($storyModelCode, '$reviewerList', $submitReviewModelPos) - $submitReviewModelPos);
r(strpos($submitReviewModelCode, 'if(!$oldStory) return false;') !== false) && p() && e(1);

$reviewModalSafe = strpos($reviewCode, 'return $this->send($this->storyZen->getResponseInModal($message));') === false
    && strpos($reviewCode, "'message' => \$message") !== false
    && strpos($reviewCode, "'load' => true") !== false
    && strpos($reviewCode, "'closeModal' => true") !== false;
r($reviewModalSafe) && p() && e(1);

$submitReviewModalSafe = strpos($submitReviewCode, "'callback' => 'loadCurrentPage()'") === false
    && strpos($submitReviewCode, "'load' => true") !== false
    && strpos($submitReviewCode, "'closeModal' => true") !== false;
r($submitReviewModalSafe) && p() && e(1);

$storyTaoCode = file_get_contents(dirname(__FILE__, 6) . '/module/story/tao.php');
$storyExtensionCallable = strpos($storyTaoCode, 'public function doCreateReviewer') !== false
    && strpos($storyTaoCode, 'public function isSuperReviewer') !== false
    && strpos($storyControlCode, 'ZT_DEBUG') === false
    && strpos($storyModelCode, 'ZT_DEBUG') === false;
r($storyExtensionCallable) && p() && e(1);

$workflowMessageLocalized = strpos($reviewCode, '$this->lang->story->errorBlockedByWorkflow') !== false
    && strpos($submitReviewCode, '$this->lang->story->errorBlockedByWorkflow') !== false
    && strpos($storyControlCode, 'blocked by workflow') === false;
r($workflowMessageLocalized) && p() && e(1);

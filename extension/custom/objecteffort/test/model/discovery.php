#!/usr/bin/env php
<?php
include dirname(__FILE__, 6) . '/test/lib/init.php';

/**

title=测试 objecteffort 扩展发现和 Hook 合并;
timeout=0
cid=objecteffort

- 自定义模块路径位于 extension/custom @1
- Bug 列表 Hook 可被 ZIN UI 扩展路径发现 @1
- 产品需求列表 Hook 可被 ZIN UI 扩展路径发现 @1
- 执行燃尽图 tao Hook 被合并到缓存文件 @1
- 项目统计 tao Hook 被合并到缓存文件 @1
- 项目工时 model Hook 被合并到缓存文件 @1
- 状态动作 Hook 被合并到缓存文件 @1
- 列表 Hook 支持 dtable、异步渲染、子需求并过滤关闭对象 @1
- 需求和Bug编辑页仅保留弹窗登记入口且不向Bug核心表单提交工时字段 @1
- 我的地盘任务、需求、Bug列表均提供登记工时入口 @1
- 登记工时弹窗使用批量录入样式，默认3行最多10行，无预计录入列，剩余工时可选填 @1
- 任务登记工时也支持一次最多10条记录 @1
- 管理员编辑任务时可修改团队或父任务的最初预计工时 @1

*/

$customRoot = dirname(__FILE__, 4);

$modulePath = $tester->app->getModulePath('', 'objecteffort');
r(strpos($modulePath, $customRoot . DS . 'objecteffort' . DS) === 0) && p() && e('1'); // 自定义模块路径位于 extension/custom

$bugViewPaths = $tester->app->getModuleExtPath('bug', 'ui');
$bugHooks     = glob($bugViewPaths['custom'] . 'browse.*.html.hook.php');
r(in_array($customRoot . DS . 'bug' . DS . 'ext' . DS . 'ui' . DS . 'browse.objecteffort.html.hook.php', $bugHooks)) && p() && e('1'); // Bug 列表 Hook 可被 ZIN UI 扩展路径发现

$productViewPaths = $tester->app->getModuleExtPath('product', 'ui');
$productHooks     = glob($productViewPaths['custom'] . 'browse.*.html.hook.php');
r(in_array($customRoot . DS . 'product' . DS . 'ext' . DS . 'ui' . DS . 'browse.objecteffort.html.hook.php', $productHooks)) && p() && e('1'); // 产品需求列表 Hook 可被 ZIN UI 扩展路径发现

$executionTaoFile = $tester->app->setTargetFile('execution', '', 'tao');
$executionTaoCode = file_get_contents($executionTaoFile);
r(strpos($executionTaoCode, "getSummaryByExecution") !== false) && p() && e('1'); // 执行燃尽图 tao Hook 被合并到缓存文件

$programTaoFile = $tester->app->setTargetFile('program', '', 'tao');
$programTaoCode = file_get_contents($programTaoFile);
r(strpos($programTaoCode, "getSummaryByProject") !== false) && p() && e('1'); // 项目统计 tao Hook 被合并到缓存文件

$projectModelFile = $tester->app->setTargetFile('project', '', 'model');
$projectModelCode = file_get_contents($projectModelFile);
r(strpos($projectModelCode, "loadModel('objecteffort')->getSummaryByProject") !== false) && p() && e('1'); // 项目工时 model Hook 被合并到缓存文件

$actionModelFile = $tester->app->setTargetFile('action', '', 'model');
$actionModelCode = file_get_contents($actionModelFile);
r(strpos($actionModelCode, "refreshObjectStatistics") !== false) && p() && e('1'); // 状态动作 Hook 被合并到缓存文件

$bugBrowseHook     = file_get_contents($customRoot . DS . 'bug' . DS . 'ext' . DS . 'view' . DS . 'browse.objecteffort.html.hook.php');
$productBrowseHook = file_get_contents($customRoot . DS . 'product' . DS . 'ext' . DS . 'view' . DS . 'browse.objecteffort.html.hook.php');
$supportsDtable    = strpos($bugBrowseHook, "closest('[data-col], td')") !== false && strpos($productBrowseHook, 'MutationObserver') !== false && strpos($bugBrowseHook, 'allowedObjectEffort') !== false && strpos($productBrowseHook, "status != 'closed'") !== false && strpos($productBrowseHook, 'story->children') !== false;
r($supportsDtable) && p() && e('1'); // 列表 Hook 支持 dtable 单元格和异步渲染

$storyEditCode = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'story' . DS . 'ui' . DS . 'edit.html.php');
$bugEditCode   = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'bug' . DS . 'ui' . DS . 'edit.html.php');
$bugFormCode   = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'bug' . DS . 'config' . DS . 'form.php');
$inlineEffort  = strpos($storyEditCode, '$effortLink') !== false
    && strpos($storyEditCode, "setData('toggle', 'modal')") !== false
    && strpos($storyEditCode, 'recordObjectEffort') === false
    && strpos($storyEditCode, 'objecteffortConsumed') === false
    && strpos($storyEditCode, 'objecteffortEstimate') === false
    && strpos($bugEditCode, '$effortLink') !== false
    && strpos($bugEditCode, "setData('toggle', 'modal')") !== false
    && strpos($bugEditCode, 'recordObjectEffort') === false
    && strpos($bugEditCode, 'objecteffortConsumed') === false
    && strpos($bugEditCode, 'objecteffortEstimate') === false
    && strpos($bugFormCode, "form->edit['consumed']") === false
    && strpos($bugFormCode, "form->edit['left']") === false
    && strpos($bugFormCode, "form->edit['estimate']") === false;
r($inlineEffort) && p() && e('1'); // 需求和 Bug 编辑页仅保留弹窗入口，且 Bug 核心编辑表单未加入工时字段

$myTableCode = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'my' . DS . 'config' . DS . 'table.php');
$myWorkhourEntry = strpos($myTableCode, "loadLang('objecteffort')") !== false
    && strpos($myTableCode, "\$config->my->task->actionList['record']") !== false
    && strpos($myTableCode, "\$config->my->requirement->actionList['recordWorkhour']") !== false
    && strpos($myTableCode, "\$config->my->story->actionList['recordWorkhour']") !== false
    && strpos($myTableCode, "\$config->my->bug->actionList['recordWorkhour']") !== false
    && strpos($myTableCode, "'objectType=requirement&objectID={id}'") !== false
    && strpos($myTableCode, "'objectType=story&objectID={id}'") !== false
    && strpos($myTableCode, "'objectType=bug&objectID={id}'") !== false;
r($myWorkhourEntry) && p() && e('1'); // 我的地盘三类列表均可直接登记工时

$recordViewCode = file_get_contents($customRoot . DS . 'objecteffort' . DS . 'view' . DS . 'record.html.php');
$recordFormCode = file_get_contents($customRoot . DS . 'objecteffort' . DS . 'config' . DS . 'form.php');
$batchRecordUI  = strpos($recordViewCode, 'formBatchPanel') !== false
    && strpos($recordViewCode, '$defaultRows') !== false
    && strpos($recordViewCode, 'for($i = 0; $i < 3; $i ++)') !== false
    && strpos($recordViewCode, 'set::maxRows(10)') !== false
    && strpos($recordViewCode, "set::name('estimate')") === false
    && strpos($recordFormCode, "recordBatch['estimate']") === false
    && strpos($recordViewCode, "set::name('left')") !== false
    && strpos($recordViewCode, "set::required(false)") !== false
    && strpos($recordFormCode, "form->record['left']") !== false
    && strpos($recordFormCode, "'required' => false") !== false;
r($batchRecordUI) && p() && e('1'); // 批量录入 UI 默认 3 行、最多 10 行，left 非必填

$taskRecordViewCode = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'task' . DS . 'ui' . DS . 'recordworkhour.html.php');
r(strpos($taskRecordViewCode, 'set::maxRows(10)') !== false) && p() && e('1'); // 任务登记工时支持最多 10 行

$taskEditCode = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'task' . DS . 'ui' . DS . 'edit.html.php');
$taskEditJS   = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'task' . DS . 'js' . DS . 'edit.ui.js');
$taskTeamCode = file_get_contents(dirname(__FILE__, 6) . DS . 'module' . DS . 'task' . DS . 'ui' . DS . 'taskteam.html.php');
$adminEstimateEditable = strpos($taskEditCode, "jsVar('isAdmin'") !== false
    && strpos($taskEditCode, '&& !$app->user->admin ? set::readonly(true) : null') !== false
    && strpos($taskEditCode, '!$app->user->admin && $member->memberDisabled') !== false
    && strpos($taskEditJS, 'if(!isAdmin)') !== false
    && strpos($taskTeamCode, '!$app->user->admin && $memberDisabled') !== false;
r($adminEstimateEditable) && p() && e('1'); // 管理员编辑任务时预计工时不因团队或父任务被只读

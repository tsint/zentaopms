#!/usr/bin/env php
<?php
/**
title=测试 workflowflowchart 真实模型流转拦截;
cid=0

- 禁止的研发需求关闭操作返回失败 @1
- 研发需求状态未改变 @active
- 禁止的用户需求关闭操作返回失败 @1
- 用户需求状态未改变 @active
- 禁止的Bug解决操作返回失败 @1
- Bug状态未改变 @active
- 被拒绝的操作未写入动作日志 @1
- 研发需求关闭按钮被规则禁用 @1
- Bug解决按钮被规则禁用 @1

*/
include dirname(__FILE__, 6) . '/test/lib/init.php';

su('admin');
global $tester;
$workflow = $tester->loadModel('workflowflowchart');
$storyModel = $tester->loadModel('story');
$bugModel = $tester->loadModel('bug');
$workflow->dao->begin();

$removeRoute = function(array $definition, string $source, string $target, string $action): array
{
    $definition['enabled'] = true;
    $definition['edges'] = array_values(array_filter($definition['edges'], function($edge) use ($source, $target, $action)
    {
        return !($edge['source'] == $source && $edge['target'] == $target && $edge['action'] == $action);
    }));
    return $definition;
};

$workflow->saveDefinition('story', $removeRoute($workflow->getDefaultDefinition('story'), 'active', 'closed', 'close'));
$workflow->saveDefinition('requirement', $removeRoute($workflow->getDefaultDefinition('requirement'), 'active', 'closed', 'close'));
$workflow->saveDefinition('bug', $removeRoute($workflow->getDefaultDefinition('bug'), 'active', 'resolved', 'resolve'));

$now = helper::now();
$workflow->dao->insert(TABLE_STORY)->data((object)array('title' => 'Workflow TDD Story', 'type' => 'story', 'status' => 'active', 'openedBy' => 'admin', 'openedDate' => $now))->exec();
$storyID = (int)$workflow->dao->lastInsertID();
$workflow->dao->insert(TABLE_STORY)->data((object)array('title' => 'Workflow TDD Requirement', 'type' => 'requirement', 'status' => 'active', 'openedBy' => 'admin', 'openedDate' => $now))->exec();
$requirementID = (int)$workflow->dao->lastInsertID();
$workflow->dao->insert(TABLE_BUG)->data((object)array('title' => 'Workflow TDD Bug', 'status' => 'active', 'openedBy' => 'admin', 'openedDate' => $now))->exec();
$bugID = (int)$workflow->dao->lastInsertID();
$actionsBefore = $workflow->dao->select('COUNT(*)')->from(TABLE_ACTION)->where('objectID')->in(array($storyID, $requirementID, $bugID))->fetch('COUNT(*)');

dao::$errors = array();
$storyResult = $storyModel->close($storyID, (object)array('closedReason' => 'cancel', 'comment' => ''));
$storyStatus = $workflow->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch('status');

dao::$errors = array();
$requirementResult = $storyModel->close($requirementID, (object)array('closedReason' => 'cancel', 'comment' => ''));
$requirementStatus = $workflow->dao->select('status')->from(TABLE_STORY)->where('id')->eq($requirementID)->fetch('status');

dao::$errors = array();
$bug = (object)array('id' => $bugID, 'status' => 'resolved', 'resolution' => 'fixed', 'resolvedBuild' => 'trunk', 'comment' => '');
$bugResult = $bugModel->resolve($bug);
$bugStatus = $workflow->dao->select('status')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch('status');
$actionsAfter = $workflow->dao->select('COUNT(*)')->from(TABLE_ACTION)->where('objectID')->in(array($storyID, $requirementID, $bugID))->fetch('COUNT(*)');
$storyObject = $workflow->dao->select('*')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$bugObject = $workflow->dao->select('*')->from(TABLE_BUG)->where('id')->eq($bugID)->fetch();
$storyButtonDisabled = !$storyModel::isClickable($storyObject, 'close');
$bugButtonDisabled = !$bugModel::isClickable($bugObject, 'resolve');

$workflow->dao->rollback();

r($storyResult === false) && p() && e(1);
r($storyStatus) && p() && e('active');
r($requirementResult === false) && p() && e(1);
r($requirementStatus) && p() && e('active');
r($bugResult === false) && p() && e(1);
r($bugStatus) && p() && e('active');
r($actionsBefore == $actionsAfter) && p() && e(1);
r($storyButtonDisabled) && p() && e(1);
r($bugButtonDisabled) && p() && e(1);

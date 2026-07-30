#!/usr/bin/env php
<?php
/**
title=测试 storyModel->getExecutionStories() 使用产品级未关闭状态。
timeout=0
cid=0

- 产品级自定义未关闭状态参与执行需求查询 @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';

su('admin');

global $tester;
$storyModel      = $tester->loadModel('story');
$statetransition = $tester->loadModel('statetransition');

$productID   = 1000001;
$executionID = 1000001;
$storyID     = 1000001;
$closedID    = 1000002;

$tester->dao->exec("DELETE FROM " . TABLE_PROJECTSTORY . " WHERE project = $executionID");
$tester->dao->exec("DELETE FROM " . TABLE_STORY . " WHERE id IN ($storyID, $closedID)");
$tester->dao->exec("DELETE FROM " . TABLE_PROJECT . " WHERE id = $executionID");
$tester->dao->exec("DELETE FROM " . TABLE_PRODUCT . " WHERE id = $productID");
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('objectType')->eq('story')->andWhere('productID')->in(array(0, $productID))->exec();

$tester->dao->exec("INSERT INTO " . TABLE_PRODUCT . " (`id`, `name`, `code`, `status`, `type`, `createdBy`, `createdDate`) VALUES ($productID, 'Product Status Test', 'product-status-test', 'normal', 'normal', 'admin', NOW())");
$tester->dao->exec("INSERT INTO " . TABLE_PROJECT . " (`id`, `type`, `name`, `code`, `status`, `storyType`, `hasProduct`, `openedBy`, `openedDate`) VALUES ($executionID, 'sprint', 'Execution Status Test', 'execution-status-test', 'doing', 'story', 1, 'admin', NOW())");
$tester->dao->exec("INSERT INTO " . TABLE_STORY . " (`id`, `root`, `path`, `product`, `title`, `type`, `status`, `stage`, `openedBy`, `openedDate`, `deleted`) VALUES ($storyID, $storyID, ',$storyID,', $productID, 'Product status story', 'story', 'prodopen', 'wait', 'admin', NOW(), 0)");
$tester->dao->exec("INSERT INTO " . TABLE_STORY . " (`id`, `root`, `path`, `product`, `title`, `type`, `status`, `stage`, `openedBy`, `openedDate`, `deleted`) VALUES ($closedID, $closedID, ',$closedID,', $productID, 'Closed story', 'story', 'closed', 'wait', 'admin', NOW(), 0)");
$tester->dao->exec("INSERT INTO " . TABLE_PROJECTSTORY . " (`project`, `product`, `story`, `version`, `order`) VALUES ($executionID, $productID, $storyID, 1, 1), ($executionID, $productID, $closedID, 1, 2)");

$statetransition->clearCache();

$productDefinition = $statetransition->getDefaultDefinition('story');
$productDefinition['statuses'][] = array(
    'key'        => 'prodopen',
    'label'      => array('zh_cn' => '产品打开', 'en' => 'Product Open'),
    'category'   => 'normal',
    'color'      => '#1abc9c',
    'isSystem'   => false,
    'isEntry'    => false,
    'fieldRules' => new stdClass(),
);
$statetransition->saveDefinition('story', $productID, $productDefinition, 0, true);

$_SESSION['storyBrowseType'] = 'changing';
$stories = $storyModel->getExecutionStories($executionID, $productID, 't1.`order`_desc', 'byProduct', (string)$productID);
unset($_SESSION['storyBrowseType']);

r(count($stories)) && p() && e('1'); // 产品级自定义未关闭状态参与执行需求查询

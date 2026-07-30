#!/usr/bin/env php
<?php
/**
title=测试 storyModel->getUnclosedStatusKeys();
timeout=0
cid=18649

- 获取草稿状态属性0 @draft
- 获取评审中状态属性1 @reviewing
- 获取激活状态属性2 @active
- 获取变更中状态属性3 @changing
- 获取空值 @~~
- 存在工作流定义时获取未关闭状态 @draft
- 读取产品级状态后全局未关闭状态不能被污染 @0
*/
include dirname(__FILE__, 5) . "/test/lib/init.php";

global $tester;
$storyModel = $tester->loadModel('story');

r($storyModel->getUnclosedStatusKeys()) && p('0') && e('draft');     //获取草稿状态
r($storyModel->getUnclosedStatusKeys()) && p('1') && e('reviewing'); //获取评审中状态
r($storyModel->getUnclosedStatusKeys()) && p('2') && e('active');    //获取激活状态
r($storyModel->getUnclosedStatusKeys()) && p('3') && e('changing');  //获取变更中状态
r($storyModel->getUnclosedStatusKeys()) && p('4') && e('~~');        //获取空值

$statetransition = $tester->loadModel('statetransition');
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->andWhere('objectType')->eq('story')->exec();
$statetransition->clearCache();
$statetransition->saveDefinition('story', 0, $statetransition->getDefaultDefinition('story'), 0, true);

r($storyModel->getUnclosedStatusKeys()) && p('0') && e('draft');      //存在工作流定义时获取未关闭状态

$productID = 1000001;
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

$storyModel->getUnclosedStatusKeys($productID);
r((int)in_array('prodopen', $storyModel->getUnclosedStatusKeys(0), true)) && p() && e('0'); //读取产品级状态后全局未关闭状态不能被污染

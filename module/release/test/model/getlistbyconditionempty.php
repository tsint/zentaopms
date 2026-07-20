#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
title=测试 releaseModel->getListByCondition() 空条件关联查询;
timeout=0
cid=0

- showRelated 为 true 且没有任何筛选条件时不查询全量发布 @0
*/

include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$release = new stdclass();
$release->id          = 900001;
$release->project     = 1;
$release->product     = 1;
$release->branch      = 0;
$release->build       = 0;
$release->name        = 'Release for empty condition';
$release->system      = 0;
$release->marker      = 0;
$release->date        = date('Y-m-d');
$release->stories     = '';
$release->bugs        = '';
$release->leftBugs    = '';
$release->desc        = '';
$release->status      = 'normal';
$release->subStatus   = '';
$release->notify      = '';
$release->createdBy   = 'admin';
$release->createdDate = helper::now();
$release->deleted     = 0;

$tester->dao->delete()->from(TABLE_RELEASE)->where('id')->eq($release->id)->exec();
$tester->dao->insert(TABLE_RELEASE)->data($release)->exec();

$releaseTest = new releaseModelTest();

r($releaseTest->getListByConditionCountTest(array(), 0, true)) && p() && e('0'); // showRelated 为 true 且没有任何筛选条件时不查询全量发布

$tester->dao->delete()->from(TABLE_RELEASE)->where('id')->eq($release->id)->exec();
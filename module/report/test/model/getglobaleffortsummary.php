#!/usr/bin/env php
<?php
declare(strict_types=1);
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->dao->delete()->from(TABLE_EFFORT)->exec();
$objectEffortTable = $tester->config->db->prefix . 'objecteffort';
if($tester->dbh->query('SHOW TABLES LIKE ' . $tester->dbh->quote($objectEffortTable))->fetch()) $tester->dbh->query("DELETE FROM `$objectEffortTable`");

$rows = array(
    array('task', 101, 1, 11, 111, 'admin', '2026-07-01', 2, 0),
    array('task', 102, ',1,2,', 11, 111, 'admin', '2026-07-02', 3, 0),
    array('task', 103, 1, 11, 111, 'dev1',  '2026-07-03', 4, 0),
    array('task', 104, 1, 12, 111, 'dev1',  '2026-07-04', 5, 0),
    array('requirement', 201, 2, 21, 211, 'dev1',  '2026-07-05', 6, 0),
    array('story',       202, 2, 21, 211, 'test1', '2026-07-06', 7, 0),
    array('bug',         301, 3, 31, 311, 'test1', '2026-07-07', 8, 0),
    array('task',        105, 1, 11, 111, 'admin', '2026-08-01', 9, 1),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => "{$row[0]} work", 'date' => $row[6], 'consumed' => $row[7], 'left' => 0, 'deleted' => $row[8], 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');
/**
title=测试 reportModel->getGlobalEffortSummary();
timeout=0
cid=0

- 全局汇总包含任务和需求工时
 - 属性records @7
 - 属性consumed @35
 - 属性productCount @3
 - 属性projectCount @4
 - 属性taskCount @4
 - 属性requirementCount @2
 - 属性userCount @3
- 用户需求与研发需求独立统计
 - 属性records @1
 - 属性consumed @6
 - 属性taskCount @0
 - 属性requirementCount @1
- 按人员筛选
 - 属性records @3
 - 属性consumed @15
 - 属性userCount @1
- 普通工时多产品格式按产品筛选
 - 属性records @3
 - 属性consumed @16
 - 属性productCount @2
*/

$report = new reportModelTest();

r($report->getGlobalEffortSummaryTest(array('begin' => '2026-07-01', 'end' => '2026-07-31'))) && p('records,consumed,productCount,projectCount,taskCount,requirementCount,userCount') && e('7,35,3,4,4,2,3'); // 全局汇总包含任务和需求工时
r($report->getGlobalEffortSummaryTest(array('objectType' => 'requirement', 'begin' => '2026-07-01', 'end' => '2026-07-31'))) && p('records,consumed,taskCount,requirementCount') && e('1,6,0,1'); // 用户需求与研发需求独立统计
r($report->getGlobalEffortSummaryTest(array('account' => 'dev1', 'begin' => '2026-07-01', 'end' => '2026-07-31'))) && p('records,consumed,userCount') && e('3,15,1'); // 按人员筛选
r($report->getGlobalEffortSummaryTest(array('product' => 2, 'begin' => '2026-07-01', 'end' => '2026-07-31'))) && p('records,consumed,productCount') && e('3,16,2'); // 普通工时多产品格式按产品筛选

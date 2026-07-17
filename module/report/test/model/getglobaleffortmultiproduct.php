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
    array('task',        101, ',1,2,', 11, 111, 'admin', '2026-07-01', 4, -1, 'multi product task'),
    array('task',        102, '1',      11, 111, 'admin', '2026-07-02', 2,  5, 'single product task'),
    array('requirement', 201, '2',      21, 211, 'dev1',  '2026-07-03', 6, 12, 'product 2 requirement'),
    array('bug',         301, '3',      31, 311, 'test1', '2026-07-04', 8,  0, 'product 3 bug'),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => $row[9], 'date' => $row[6], 'consumed' => $row[7], 'left' => $row[8], 'deleted' => 0, 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');
/**
title=测试 reportModel 全局工时多产品字段兼容;
timeout=0
cid=0

- 全局汇总去重统计多产品字段
 - 属性records @4
 - 属性consumed @20
 - 属性productCount @3
- 产品 2 筛选命中普通工时多产品记录
 - 属性records @2
 - 属性consumed @10
 - 属性productCount @2
- 产品分布中多产品记录同时归属到两个产品 @2:2:10,3:1:8,1:2:6
- 产品成本进度中多产品记录不分摊，重复归属 @2:2:10:11:0.48:1:high,3:1:8:0:1:0:low,1:2:6:4:0.6:1:high
- CSV 保留多产品明细，不退化为 0 @1
*/

$report  = new reportModelTest();
$filters = array('begin' => '2026-07-01', 'end' => '2026-07-31');

r($report->getGlobalEffortSummaryTest($filters)) && p('records,consumed,productCount') && e('4,20,3'); // 全局汇总去重统计多产品字段
r($report->getGlobalEffortSummaryTest($filters + array('product' => 2))) && p('records,consumed,productCount') && e('2,10,2'); // 产品 2 筛选命中普通工时多产品记录
r($report->getGlobalEffortDistributionTest('product', $filters)) && p() && e('2:2:10,3:1:8,1:2:6'); // 产品分布中多产品记录同时归属到两个产品
r($report->getGlobalEffortCostProgressTest('product', $filters)) && p() && e('2:2:10:11:0.48:1:high,3:1:8:0:1:0:low,1:2:6:4:0.6:1:high'); // 产品成本进度中多产品记录不分摊，重复归属

$csv = $report->buildGlobalEffortCSVTest($filters + array('product' => 2));
r(strpos($csv, '1,2') !== false && strpos($csv, ',0,') === false) && p() && e('1'); // CSV 保留多产品明细，不退化为 0

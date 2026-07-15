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
    array('task',        101, 1, 11, 111, 'admin', '2026-07-01', 3, 7),
    array('task',        101, 1, 11, 111, 'admin', '2026-07-02', 2, 5),
    array('task',        102, 1, 11, 111, 'dev1',  '2026-07-03', 4, -1),
    array('requirement', 201, 2, 21, 211, 'dev1',  '2026-07-04', 2, 12),
    array('story',       202, 2, 21, 211, 'test1', '2026-07-05', 1, 9),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => "{$row[0]} work", 'date' => $row[6], 'consumed' => $row[7], 'left' => $row[8], 'deleted' => 0, 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');
/**
title=测试 reportModel->getGlobalEffortCostProgress();
timeout=0
cid=0

- 项目成本进度风险 @11:2:9:4:0.69:1:high,21:2:3:21:0.13:0:medium

- 产品成本进度风险 @1:2:9:4:0.69:1:high,2:2:3:21:0.13:0:medium
*/

$report  = new reportModelTest();
$filters = array('begin' => '2026-07-01', 'end' => '2026-07-31');

r($report->getGlobalEffortCostProgressTest('project', $filters)) && p() && e('11:2:9:4:0.69:1:high,21:2:3:21:0.13:0:medium'); // 项目成本进度风险
r($report->getGlobalEffortCostProgressTest('product', $filters)) && p() && e('1:2:9:4:0.69:1:high,2:2:3:21:0.13:0:medium');  // 产品成本进度风险
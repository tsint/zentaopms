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
    array('task', 101, 1, 11, 111, 'admin', '2026-07-01', 2),
    array('task', 102, 1, 11, 111, 'admin', '2026-07-02', 3),
    array('task', 103, 1, 11, 111, 'dev1',  '2026-07-03', 4),
    array('requirement', 201, 2, 21, 211, 'dev1',  '2026-07-04', 6),
    array('story',       202, 2, 21, 211, 'dev1',  '2026-07-05', 7),
    array('bug',         301, 3, 31, 311, 'test1', '2026-07-06', 8),
    array('task',        104, 1, 11, 111, 'admin', '2026-07-07', 5),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => "{$row[0]} work", 'date' => $row[6], 'consumed' => $row[7], 'left' => 0, 'deleted' => 0, 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');
/**
title=测试 reportModel->getGlobalEffortDistribution();
timeout=0
cid=0

- 按产品分布 @1:4:14,2:2:13,3:1:8

- 按人员分布 @dev1:3:17,admin:3:10,test1:1:8

- 按用户需求分布 @201:1:6
*/

$report  = new reportModelTest();
$filters = array('begin' => '2026-07-01', 'end' => '2026-07-31');

r($report->getGlobalEffortDistributionTest('product', $filters)) && p() && e('1:4:14,2:2:13,3:1:8');            // 按产品分布
r($report->getGlobalEffortDistributionTest('account', $filters)) && p() && e('dev1:3:17,admin:3:10,test1:1:8'); // 按人员分布
r($report->getGlobalEffortDistributionTest('requirement', $filters)) && p() && e('201:1:6');                    // 按用户需求分布
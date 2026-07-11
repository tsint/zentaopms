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
    array('task',        101, 1, 11, 111, 'admin', '2026-07-01', 9),
    array('task',        102, 1, 11, 111, 'admin', '2026-07-01', 4),
    array('requirement', 201, 2, 21, 211, 'dev1',  '2026-07-02', 3),
    array('story',       202, 2, 21, 211, 'test1', '2026-07-03', 2),
    array('bug',         301, 3, 31, 311, 'admin', '2026-07-04', 7),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => "{$row[0]} work", 'date' => $row[6], 'consumed' => $row[7], 'left' => 0, 'deleted' => 0, 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');

/**

title=测试 reportModel->getGlobalEffortHealth();
cid=global-effort
pid=1

*/

$report = new reportModelTest();
$health = $report->getGlobalEffortHealthTest(array('begin' => '2026-07-01', 'end' => '2026-07-31'));

r($health) && p('activeUsers,activeDays,totalConsumed,avgHoursPerUser,avgHoursPerDay') && e('3,4,25,8.33,6.25'); // 健康分析基础指标
r($health) && p('overloadDays,topAccount,topAccountConsumed,topAccountShare,riskLevel') && e('1,admin,20,0.8,high'); // 超负荷和高集中度风险

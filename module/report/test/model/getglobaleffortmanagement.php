#!/usr/bin/env php
<?php
declare(strict_types=1);
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->dao->delete()->from(TABLE_EFFORT)->exec();
$objectEffortTable = $tester->config->db->prefix . 'objecteffort';
if($tester->dbh->query('SHOW TABLES LIKE ' . $tester->dbh->quote($objectEffortTable))->fetch()) $tester->dbh->query("DELETE FROM `$objectEffortTable`");
$tester->dao->delete()->from(TABLE_TASK)->where('id')->in('9001,9002')->exec();

$tasks = array(
    (object)array('id' => 9001, 'name' => 'Linked task', 'story' => 3001, 'estimate' => 20, 'consumed' => 0, 'left' => 0, 'status' => 'doing', 'deleted' => '0'),
    (object)array('id' => 9002, 'name' => 'Standalone task', 'story' => 0, 'estimate' => 10, 'consumed' => 0, 'left' => 0, 'status' => 'doing', 'deleted' => '0'),
);
foreach($tasks as $task) $tester->dao->insert(TABLE_TASK)->data($task)->exec();

$rows = array(
    array(1, 'epic',        1001, 1, 11, 111, 'admin', '2026-07-01', 2, 0),
    array(2, 'requirement', 2001, 1, 11, 111, 'dev1',  '2026-07-02', 3, 0),
    array(3, 'story',       3001, 1, 11, 111, 'dev1',  '2026-07-03', 4, 0),
    array(4, 'task',        9001, 1, 11, 111, 'dev1',  '2026-07-10', 5, 0),
    array(5, 'bug',         4001, 2, 22, 222, 'test1', '2026-07-04', 6, 0),
    array(6, 'task',        9002, 2, 22, 222, 'admin', '2026-07-05', 7, 0),
    array(7, 'task',        9002, 2, 22, 222, 'admin', '2026-07-20', 1, 0),
);
foreach($rows as $row)
{
    $effort = (object)array('id' => $row[0], 'objectType' => $row[1], 'objectID' => $row[2], 'product' => (string)$row[3], 'project' => $row[4], 'execution' => $row[5], 'account' => $row[6], 'work' => "{$row[1]} work", 'date' => $row[7], 'consumed' => $row[8], 'left' => 0, 'deleted' => $row[9], 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');

/**

title=测试 reportModel->getGlobalEffortManagement();
cid=global-effort
pid=1

*/

$report  = new reportModelTest();
$filters = array('begin' => '2026-07-01', 'end' => '2026-07-31', 'staleDays' => 5);

r($report->getGlobalEffortManagementMetricsTest($filters)) && p('totalConsumed,estimatedConsumed,estimatedTotal,estimatedPercent') && e('28,13,30,0.43'); // 总工时和实际/预估
r($report->getGlobalEffortObjectTypeDistributionTest($filters)) && p() && e('task:任务:13:0.4643,bug:Bug:6:0.2143,story:研发需求:4:0.1429,requirement:用户需求:3:0.1071,epic:业务需求:2:0.0714'); // 对象类型分布
r($report->getGlobalEffortStaleObjectsTest($filters)) && p() && e('epic:1001:30,requirement:2001:29,story:3001:28,bug:4001:27,task:9001:21,task:9002:15'); // 停滞对象
r($report->getGlobalEffortAccountStackTest($filters)) && p() && e('dev1:12:task:5|story:4|requirement:3,admin:10:task:8|epic:2,test1:6:bug:6'); // 人员堆叠分布
r($report->getGlobalEffortManagementMetricsTest($filters + array('objectType' => 'story', 'objectID' => 3001, 'includeRelated' => 1))) && p('totalConsumed') && e('9'); // 勾选关联对象时包含需求关联任务

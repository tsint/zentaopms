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
    array('task',        101, 1, 11, 111, 'admin', '2026-07-01', 2, 'task work'),
    array('requirement', 201, 2, 21, 211, 'dev1',  '2026-07-02', 6, 'requirement work'),
    array('story',       202, 2, 21, 211, 'test1', '2026-07-03', 7, 'story work'),
);
foreach($rows as $i => $row)
{
    $effort = (object)array('id' => $i + 1, 'objectType' => $row[0], 'objectID' => $row[1], 'product' => (string)$row[2], 'project' => $row[3], 'execution' => $row[4], 'account' => $row[5], 'work' => $row[8], 'date' => $row[6], 'consumed' => $row[7], 'left' => 0, 'deleted' => 0, 'vision' => 'rnd');
    $tester->dao->insert(TABLE_EFFORT)->data($effort)->exec();
}

su('admin');
/**
title=测试 reportModel->buildGlobalEffortCSV();
timeout=0
cid=0

- CSV 包含表头 @1
- CSV 带 UTF-8 BOM，避免 Excel 打开中文标题乱码 @1
- CSV 用户需求筛选不混入研发需求 @1
- 需求筛选排除任务工时 @1
*/

$report = new reportModelTest();
$csv    = $report->buildGlobalEffortCSVTest(array('objectType' => 'requirement', 'begin' => '2026-07-01', 'end' => '2026-07-31'));

r(strpos($csv, '来源,日期,产品,项目,执行,对象类型,对象ID,人员,耗时,剩余,工作内容') !== false) && p() && e('1'); // CSV 包含表头
r(substr($csv, 0, 3) === "\xEF\xBB\xBF") && p() && e('1');                                                      // CSV 带 UTF-8 BOM，避免 Excel 打开中文标题乱码
r(strpos($csv, 'requirement work') !== false && strpos($csv, 'story work') === false) && p() && e('1');           // CSV 用户需求筛选不混入研发需求
r(strpos($csv, 'task work') === false) && p() && e('1');                                                         // 需求筛选排除任务工时
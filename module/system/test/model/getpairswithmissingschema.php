#!/usr/bin/env php
<?php
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';
su('admin');
/**
title=测试 systemModel::getPairs() 在应用表缺失时降级为空数组;
timeout=0
cid=18755


*/
global $tester;

$systemTest = new systemModelTest();
$system     = $tester->loadModel('system');

$table       = trim(TABLE_SYSTEM, '`');
$backupTable = $table . '_schema_guard_test';

$system->dbh->exec("DROP TABLE IF EXISTS `{$backupTable}`");
$system->dbh->exec("RENAME TABLE `{$table}` TO `{$backupTable}`");

try
{
    r($systemTest->getPairsCountTest()) && p() && e('0'); // 临时移走应用表后查询应用键值对
}
finally
{
    $system->dbh->exec("RENAME TABLE `{$backupTable}` TO `{$table}`");
}
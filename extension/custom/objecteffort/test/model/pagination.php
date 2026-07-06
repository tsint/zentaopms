#!/usr/bin/env php
<?php
include dirname(__FILE__, 6) . '/test/lib/init.php';
/**
title=测试 objecteffortModel 工时历史分页;
timeout=0
cid=0


*/

if(!defined('TABLE_OBJECTEFFORT')) define('TABLE_OBJECTEFFORT', '`zt_objecteffort`');

$objectID = random_int(15000000, 16000000);
$tester->dao->begin();
try
{
    for($i = 1; $i <= 11; $i ++)
    {
        $tester->dao->query("INSERT INTO " . TABLE_OBJECTEFFORT . " (`objectType`,`objectID`,`account`,`date`,`consumed`,`left`,`work`,`createdBy`,`createdDate`,`deleted`) VALUES ('bug',{$objectID},'admin','2026-06-30',1,0,'分页记录{$i}','admin',NOW(),'0')");
    }

    $objectEffort = $tester->loadModel('objecteffort');
    $sixRecords   = $objectEffort->getList('bug', $objectID, 6);

    $tester->app->rawModule = 'objecteffort';
    $tester->app->rawMethod = 'record';
    $tester->app->loadClass('pager', true);
    $firstPager  = new pager(0, 10, 1, 'objectEffortTest');
    $firstPage   = $objectEffort->getList('bug', $objectID, 0, $firstPager);
    $secondPager = new pager(0, 10, 2, 'objectEffortTest');
    $secondPage  = $objectEffort->getList('bug', $objectID, 0, $secondPager);

    $allIDs = array_merge(array_keys($firstPage), array_keys($secondPage));

    r(count($sixRecords)) && p() && e('6');
    r(count($firstPage)) && p() && e('10');
    r(count($secondPage)) && p() && e('1');
    r(array('first' => reset($firstPage)->content, 'second' => reset($secondPage)->content)) && p('first,second') && e('分页记录11,分页记录1');
    r(count(array_unique($allIDs))) && p() && e('11');
}
finally
{
    $tester->dao->rollBack();
}
#!/usr/bin/env php
<?php
/**
title=测试 userTao->deleteImUserDevice();
cid=0

- 执行$oldTokens @2
- 执行$oldTokens[1]
 - 属性user @1
 - 属性device @zentaoweb
 - 属性token @1
- 执行$oldTokens[2]
 - 属性user @1
 - 属性device @desktop
 - 属性token @2
- 执行$newTokens @0
- 表存在时使用真实表名检查并删除用户设备 @0

*/
include dirname(__FILE__, 5) . '/test/lib/init.php';

global $tester;
$table = $tester->config->db->prefix . 'im_userdevice';
$tester->dbh->query("DROP TABLE IF EXISTS `$table`");
$tester->dbh->query("CREATE TABLE `$table` (
    `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    `user` mediumint(8) NOT NULL DEFAULT 0,
    `device` char(40) NOT NULL DEFAULT 'default',
    `deviceID` char(40) NOT NULL DEFAULT '',
    `token` char(64) NOT NULL DEFAULT '',
    `validUntil` datetime DEFAULT NULL,
    `lastLogin` datetime DEFAULT NULL,
    `lastLogout` datetime DEFAULT NULL,
    `online` tinyint(1) NOT NULL DEFAULT 0,
    `version` char(10) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `userdevice` (`user`,`device`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$tester->dbh->query("INSERT INTO `$table` (`user`, `device`, `token`) VALUES (1, 'zentaoweb', '1'), (1, 'desktop', '2'), (2, 'zentaoweb', '3'), (2, 'desktop', '4')");

su('admin');

global $app;
$userModel = $tester->loadModel('user');

$oldTokens = $userModel->dao->select('*')->from(TABLE_IM_USERDEVICE)->where('user')->eq($app->user->id)->orderBy('id')->fetchAll('id');
$userModel->deleteImUserDevice($app->user->id);
$newTokens = $userModel->dao->select('*')->from(TABLE_IM_USERDEVICE)->where('user')->eq($app->user->id)->fetchAll('id');

$extraDevice = (object)array('user' => $app->user->id, 'device' => 'desktop-again', 'token' => 'remembered');
$userModel->dao->insert(TABLE_IM_USERDEVICE)->data($extraDevice)->exec();
$userModel->deleteImUserDevice($app->user->id);
$leftTokens = $userModel->dao->select('*')->from(TABLE_IM_USERDEVICE)->where('user')->eq($app->user->id)->fetchAll('id');

r(count($oldTokens)) && p() && e(2);
r($oldTokens[1]) && p('user,device,token') && e('1,zentaoweb,1');
r($oldTokens[2]) && p('user,device,token') && e('1,desktop,2');
r(count($newTokens)) && p() && e(0);
r(count($leftTokens)) && p() && e(0); // 表存在时使用真实表名检查并删除用户设备

$tester->dbh->query("DROP TABLE IF EXISTS `$table`");

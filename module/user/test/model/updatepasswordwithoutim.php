#!/usr/bin/env php
<?php
/**
title=测试缺少IM设备表时 userModel->updatePassword();
cid=0

- 缺少IM设备表时修改密码成功 @1
- 数据库中的密码已更新 @1

*/
include dirname(__FILE__, 5) . '/test/lib/init.php';

su('admin');

global $tester, $app;
$userModel = $tester->loadModel('user');
$table     = trim(TABLE_IM_USERDEVICE, '`');
$hasTable  = $userModel->dbh->query('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ' . $userModel->dbh->quote($table) . ' LIMIT 1')->fetchColumn();
if($hasTable) die("This test requires the optional IM device table to be absent.\n");

$userModel->dao->begin();

$random      = updateSessionRandom();
$newPassword = md5('TddPassword123!');
$user        = (object)array(
    'originalPassword' => md5($app->user->password . $random),
    'password'         => $newPassword,
    'password1'        => $newPassword,
    'password2'        => $newPassword,
    'passwordStrength' => 2,
    'passwordLength'   => 15
);

$result       = $userModel->updatePassword($user);
$passwordInDB = $userModel->dao->select('password')->from(TABLE_USER)->where('id')->eq($app->user->id)->fetch('password');
$userModel->dao->rollback();

r($result) && p() && e(1);
r($passwordInDB === $newPassword) && p() && e(1);

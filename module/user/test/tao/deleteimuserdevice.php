#!/usr/bin/env php
<?php
/**
title=测试 userTao->deleteImUserDevice();
cid=0

- IM设备表不存在时不执行删除 @0
- IM设备表存在时执行删除 @1
- 删除发生数据库异常时向上抛出 @1

*/
include dirname(__FILE__, 5) . '/test/lib/init.php';

global $tester;
$tester->loadModel('user');

class userDeviceDaoStub
{
    public $execCount = 0;
    public $throwOnExec = false;

    public function delete() {return $this;}
    public function from($table) {return $this;}
    public function where($field) {return $this;}
    public function eq($value) {return $this;}

    public function exec()
    {
        $this->execCount++;
        if($this->throwOnExec) throw new RuntimeException('delete failed');
        return 1;
    }
}

class testableUserTao extends userTao
{
    public $imUserDeviceTableExists = false;

    protected function hasImUserDeviceTable(): bool
    {
        return $this->imUserDeviceTableExists;
    }
}

$userTao = new testableUserTao();
$userTao->dao = new userDeviceDaoStub();
$userTao->deleteImUserDevice(1);
r($userTao->dao->execCount) && p() && e(0);

$userTao->imUserDeviceTableExists = true;
$userTao->deleteImUserDevice(1);
r($userTao->dao->execCount) && p() && e(1);

$userTao->dao->throwOnExec = true;
$exceptionRaised = false;
try
{
    $userTao->deleteImUserDevice(1);
}
catch (RuntimeException $e)
{
    $exceptionRaised = true;
}
r($exceptionRaised) && p() && e(1);

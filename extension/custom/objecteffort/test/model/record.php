#!/usr/bin/env php
<?php
include dirname(__FILE__, 6) . '/test/lib/init.php';
/**
title=测试 objecteffortModel 工时登记和汇总;
timeout=0
cid=0

- Bug 登记工时后返回 ID 大于 0 @1
- 非法耗时被拒绝 consumed 字段报错属性consumed @耗时必须为大于 0 的数字。
- 未来日期被拒绝属性date @日期不能为空，且不能晚于今天。
- 关闭对象被拒绝属性objectID @已关闭对象不能登记工时。
- Story 登记工时后返回 ID 大于 0 @1
- 多执行需求未指定执行时被拒绝属性execution @请选择需求所属的执行。
- 需求不能登记到未关联执行属性execution @选择的执行未关联当前需求。
- 剩余为空时按预计总工时减累计耗时自动计算属性left @5.00
- 批量登记时剩余按前序行累计耗时自动计算属性left @4.00
- 需求工时只计入选定执行
 - 属性estimate @18
 - 属性consumed @10
 - 属性left @7
- 未选定执行不包含需求工时 @0
- 未排期需求工时归入项目而不伪造执行
 - 属性project @1
 - 属性execution @0
- 项目汇总包含未排期需求
 - 属性estimate @22
 - 属性consumed @13
 - 属性left @8
- 项目统计被对象工时刷新
 - 属性estimate @22.00
 - 属性consumed @13.00
 - 属性left @8.00
 - 属性progress @61.90
- 执行统计被对象工时刷新
 - 属性estimate @18.00
 - 属性consumed @10.00
 - 属性left @7.00
 - 属性progress @58.80
- 燃尽图计算包含对象工时
 - 属性estimate @18
 - 属性left @7
 - 属性consumed @10
- 新增需求后燃尽范围故事点上升 @13
- 编辑后 consumed/left 变化
 - 属性consumed @4.00
 - 属性left @1.00
- 删除后记录软删除属性deleted @1
- 核心任务表未被对象工时修改 @0.00
- 无登记权限的用户被拒绝 @1
- 有登记权限的用户可登记 @1
- 所有者无编辑权限时仍被拒绝 @1
- 所有者有编辑权限时可操作 @1
- 非所有者即使有编辑权限也被拒绝 @1
- 关闭对象后燃尽汇总清零预计和剩余但保留耗时
 - 属性estimate @12
 - 属性consumed @11
 - 属性left @4
- 关闭动作立即刷新项目剩余工时属性left @5.00
- 关闭动作立即刷新当日燃尽
 - 属性estimate @12.00
 - 属性consumed @11.00
 - 属性left @4.00
*/

if($tester->dao->select('account')->from(TABLE_USER)->where('account')->eq('admin')->fetch('account'))
{
    $tester->dao->update(TABLE_USER)->set('deleted')->eq(0)->where('account')->eq('admin')->exec();
}
else
{
    $tester->dao->query("INSERT INTO zt_user (`account`,`realname`,`password`,`visions`,`deleted`) VALUES ('admin','admin','" . md5('123456') . "','rnd,lite,or',0)");
}

su('admin');
$tester->app->user->account = 'admin';
$tester->app->user->admin   = true;

if(!defined('TABLE_OBJECTEFFORT')) define('TABLE_OBJECTEFFORT', '`zt_objecteffort`');

$tester->dao->query("CREATE TABLE IF NOT EXISTS `zt_objecteffort` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `objectType` varchar(30) NOT NULL,
  `objectID` mediumint unsigned NOT NULL,
  `product` mediumint unsigned NOT NULL DEFAULT 0,
  `project` mediumint unsigned NOT NULL DEFAULT 0,
  `execution` mediumint unsigned NOT NULL DEFAULT 0,
  `account` varchar(30) NOT NULL,
  `date` date NOT NULL,
  `estimate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `consumed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `left` decimal(12,2) NOT NULL DEFAULT 0.00,
  `work` text NOT NULL,
  `createdBy` varchar(30) NOT NULL,
  `createdDate` datetime NOT NULL,
  `editedBy` varchar(30) NOT NULL DEFAULT '',
  `editedDate` datetime DEFAULT NULL,
  `deleted` enum('0','1') NOT NULL DEFAULT '0',
  `deletedBy` varchar(30) NOT NULL DEFAULT '',
  `deletedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object` (`objectType`, `objectID`, `deleted`),
  KEY `execution` (`execution`, `objectType`, `objectID`, `deleted`),
  KEY `project` (`project`, `deleted`),
  KEY `accountDate` (`account`, `date`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
$tester->dao->delete()->from(TABLE_OBJECTEFFORT)->exec();

$tester->dao->delete()->from(TABLE_PROJECT)->where('id')->in('900001,900002,900003')->exec();
$tester->dao->delete()->from(TABLE_PRODUCT)->where('id')->eq(900001)->exec();
$tester->dao->delete()->from(TABLE_BUG)->where('id')->eq(900001)->exec();
$tester->dao->delete()->from(TABLE_STORY)->where('id')->in('900001,900002,900003')->exec();
$tester->dao->delete()->from(TABLE_PROJECTSTORY)->where('project')->in('900001,900002,900003')->orWhere('story')->in('900001,900002,900003')->exec();
$tester->dao->delete()->from(TABLE_TASK)->where('id')->eq(900001)->exec();
$tester->dao->delete()->from(TABLE_ACTION)->where('objectType')->eq('objecteffort')->exec();
$tester->dao->delete()->from(TABLE_BURN)->where('execution')->in('900002,900003')->exec();

$tester->dao->query("INSERT INTO zt_project (`id`,`project`,`model`,`type`,`name`,`code`,`status`,`begin`,`end`,`openedBy`,`openedDate`,`deleted`) VALUES
(900001,0,'scrum','project','项目1','objecteffort-project','doing','2026-01-01','2026-12-31','admin',NOW(),'0'),
(900002,900001,'scrum','sprint','迭代1','objecteffort-execution1','doing','2026-01-01','2026-12-31','admin',NOW(),'0'),
(900003,900001,'scrum','sprint','迭代2','objecteffort-execution2','doing','2026-01-01','2026-12-31','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_product (`id`,`name`,`code`,`line`,`type`,`status`,`createdBy`,`createdDate`,`deleted`) VALUES (900001,'产品1','objecteffort-product',0,'normal','normal','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_bug (`id`,`product`,`project`,`execution`,`title`,`status`,`openedBy`,`openedDate`,`deleted`) VALUES (900001,900001,900001,900002,'Bug 1','active','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_story (`id`,`root`,`path`,`grade`,`product`,`title`,`type`,`status`,`estimate`,`stage`,`openedBy`,`openedDate`,`deleted`) VALUES (900001,900001,',900001,',1,900001,'Story 1','story','active',8,'planned','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_projectstory (`project`,`product`,`story`,`version`,`order`) VALUES (900002,900001,900001,1,1),(900003,900001,900001,1,1)");
$tester->dao->query("INSERT INTO zt_task (`id`,`project`,`execution`,`name`,`type`,`status`,`estimate`,`consumed`,`left`,`openedBy`,`openedDate`,`deleted`) VALUES (900001,900001,900002,'Task 1','devel','wait',0,0,0,'admin',NOW(),'0')");

$objectEffort = $tester->loadModel('objecteffort');

$bugEffort = new stdclass();
$bugEffort->date     = helper::today();
$bugEffort->estimate = 6;
$bugEffort->consumed = 2;
$bugEffort->left     = 3;
$bugEffort->work     = '处理 Bug';
$bugEffortID = $objectEffort->record('bug', 900001, $bugEffort);

$invalidEffort = new stdclass();
$invalidEffort->date     = helper::today();
$invalidEffort->estimate = 1;
$invalidEffort->consumed = 0;
$invalidEffort->left     = 1;
$invalidEffort->work     = '非法工时';
$objectEffort->record('bug', 900001, $invalidEffort);
$invalidErrors = dao::getError();
dao::$errors = array();

$futureEffort = clone $bugEffort;
$futureEffort->date = date('Y-m-d', strtotime('+1 day'));
$objectEffort->record('bug', 900001, $futureEffort);
$futureErrors = dao::getError();
dao::$errors = array();

$tester->dao->update(TABLE_BUG)->set('status')->eq('closed')->where('id')->eq(900001)->exec();
$objectEffort->record('bug', 900001, $bugEffort);
$closedErrors = dao::getError();
dao::$errors = array();
$tester->dao->update(TABLE_BUG)->set('status')->eq('active')->where('id')->eq(900001)->exec();

$storyEffort = new stdclass();
$storyEffort->date     = helper::today();
$storyEffort->estimate = 4;
$storyEffort->consumed = 3;
$storyEffort->left     = 1;
$storyEffort->work     = '处理需求';
$objectEffort->record('story', 900001, $storyEffort);
$ambiguousErrors = dao::getError();
dao::$errors = array();

$storyEffort->execution = 999;
$objectEffort->record('story', 900001, $storyEffort);
$unlinkedErrors = dao::getError();
dao::$errors = array();

$storyEffort->execution = 900002;
$storyEffortID = $objectEffort->record('story', 900001, $storyEffort);

$autoLeftEffort = new stdclass();
$autoLeftEffort->date     = helper::today();
$autoLeftEffort->execution = 900002;
$autoLeftEffort->estimate = 10;
$autoLeftEffort->consumed = 2;
$autoLeftEffort->left     = '';
$autoLeftEffort->work     = '自动计算剩余';
$autoLeftEffortID = $objectEffort->record('story', 900001, $autoLeftEffort);
$autoLeftRecord = $objectEffort->getByID($autoLeftEffortID);

$batchEfforts = array();
$batchEfforts[1] = new stdclass();
$batchEfforts[1]->date     = helper::today();
$batchEfforts[1]->execution = 900002;
$batchEfforts[1]->estimate = 12;
$batchEfforts[1]->consumed = 1;
$batchEfforts[1]->left     = '';
$batchEfforts[1]->work     = '批量需求工时1';
$batchEfforts[2] = new stdclass();
$batchEfforts[2]->date     = helper::today();
$batchEfforts[2]->execution = 900002;
$batchEfforts[2]->estimate = 12;
$batchEfforts[2]->consumed = 2;
$batchEfforts[2]->left     = '';
$batchEfforts[2]->work     = '批量需求工时2';
$batchEffortIDs = $objectEffort->recordBatch('story', 900001, $batchEfforts);
$batchLastEffort = $objectEffort->getByID(end($batchEffortIDs));

$tester->dao->query("INSERT INTO zt_story (`id`,`root`,`path`,`grade`,`product`,`title`,`type`,`status`,`estimate`,`stage`,`openedBy`,`openedDate`,`deleted`) VALUES (900003,900003,',900003,',1,900001,'未排期需求','requirement','active',3,'wait','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_projectstory (`project`,`product`,`story`,`version`,`order`) VALUES (900001,900001,900003,1,3)");
$projectOnlyEffort = clone $storyEffort;
unset($projectOnlyEffort->execution);
$projectOnlyEffortID = $objectEffort->record('requirement', 900003, $projectOnlyEffort);
$projectOnlyRecord = $objectEffort->getByID($projectOnlyEffortID);

$executionSummary = $objectEffort->getSummaryByExecution(array(900002));
$otherExecutionSummary = $objectEffort->getSummaryByExecution(array(900003));
$projectSummary   = $objectEffort->getSummaryByProject(array(900001));
$projectStats     = $tester->dao->select('estimate, consumed, `left`, progress')->from(TABLE_PROJECT)->where('id')->eq(900001)->fetch();
$executionStats   = $tester->dao->select('estimate, consumed, `left`, progress')->from(TABLE_EXECUTION)->where('id')->eq(900002)->fetch();
$burns            = $tester->dao->select('*')->from(TABLE_BURN)->where('execution')->eq(900002)->andWhere('date')->eq(helper::today())->fetchAll('execution');

$tester->dao->query("INSERT INTO zt_story (`id`,`root`,`path`,`grade`,`product`,`title`,`type`,`status`,`estimate`,`stage`,`openedBy`,`openedDate`,`deleted`) VALUES (900002,900002,',900002,',1,900001,'客户新增需求','requirement','active',5,'planned','admin',NOW(),'0')");
$tester->dao->query("INSERT INTO zt_projectstory (`project`,`product`,`story`,`version`,`order`) VALUES (900002,900001,900002,1,2)");
$tester->loadModel('execution')->computeBurn(900002);
$scopeAfterChange = $tester->dao->select('storyPoint')->from(TABLE_BURN)->where('execution')->eq(900002)->andWhere('date')->eq(helper::today())->fetch('storyPoint');

$editEffort = new stdclass();
$editEffort->date     = helper::today();
$editEffort->estimate = 6;
$editEffort->consumed = 4;
$editEffort->left     = 1;
$editEffort->work     = '更新 Bug 工时';
$objectEffort->update($bugEffortID, $editEffort);
$edited = $objectEffort->getByID($bugEffortID);

$objectEffort->deleteEffort($storyEffortID);
$deleted = $objectEffort->getByID($storyEffortID);

$taskAfter = $tester->dao->select('consumed')->from(TABLE_TASK)->where('id')->eq(900001)->fetch('consumed');

$tester->app->user->account = 'worker';
$tester->app->user->admin   = false;
$tester->app->user->rights  = array('rights' => array(), 'acls' => array());
common::$userPrivs = array();
$deniedRecord = $objectEffort->record('bug', 900001, $bugEffort) === false;
dao::$errors = array();

$tester->app->user->rights['rights']['objecteffort']['record'] = true;
common::$userPrivs = array();
$workerEffortID = $objectEffort->record('bug', 900001, $bugEffort);
$workerEffort = $objectEffort->getByID($workerEffortID);
$workerRecordAllowed = $workerEffortID > 0;
$workerEditDenied = !$objectEffort->canOperate($workerEffort, 'edit');
$tester->app->user->rights['rights']['objecteffort']['edit'] = true;
common::$userPrivs = array();
$workerEditAllowed = $objectEffort->canOperate($workerEffort, 'edit');
$tester->app->user->account = 'other';
common::$userPrivs = array();
$otherEditDenied = !$objectEffort->canOperate($workerEffort, 'edit');
$tester->dao->update(TABLE_BUG)->set('status')->eq('closed')->where('id')->eq(900001)->exec();
$closedExecutionSummary = $objectEffort->getSummaryByExecution(array(900002));
$tester->loadModel('action')->create('bug', 900001, 'Closed');
$statsAfterClose = $tester->dao->select('`left`')->from(TABLE_PROJECT)->where('id')->eq(900001)->fetch();
$burnAfterClose = $tester->dao->select('estimate, consumed, `left`')->from(TABLE_BURN)->where('execution')->eq(900002)->andWhere('date')->eq(helper::today())->fetch();

r($bugEffortID > 0) && p() && e('1');                                                 // Bug 登记工时后返回 ID 大于 0
r($invalidErrors) && p('consumed') && e('耗时必须为大于 0 的数字。');                  // 非法耗时被拒绝 consumed 字段报错
r($futureErrors) && p('date') && e('日期不能为空，且不能晚于今天。');                 // 未来日期被拒绝
r($closedErrors) && p('objectID') && e('已关闭对象不能登记工时。');                   // 关闭对象被拒绝
r($storyEffortID > 0) && p() && e('1');                                               // Story 登记工时后返回 ID 大于 0
r($ambiguousErrors) && p('execution') && e('请选择需求所属的执行。');                   // 多执行需求未指定执行时被拒绝
r($unlinkedErrors) && p('execution') && e('选择的执行未关联当前需求。');                // 需求不能登记到未关联执行
r($autoLeftRecord) && p('left') && e('5.00');                                          // 剩余为空时按预计总工时减累计耗时自动计算
r($batchLastEffort) && p('left') && e('4.00');                                         // 批量登记时剩余按前序行累计耗时自动计算
r($executionSummary[900002]) && p('estimate,consumed,left') && e('18,10,7');           // 需求工时只计入选定执行
r(isset($otherExecutionSummary[900003])) && p() && e('0');                            // 未选定执行不包含需求工时
r($projectOnlyRecord) && p('project,execution') && e('1,0');                          // 未排期需求工时归入项目而不伪造执行
r($projectSummary[900001]) && p('estimate,consumed,left') && e('22,13,8');             // 项目汇总包含未排期需求
r($projectStats) && p('estimate,consumed,left,progress') && e('22.00,13.00,8.00,61.90'); // 项目统计被对象工时刷新
r($executionStats) && p('estimate,consumed,left,progress') && e('18.00,10.00,7.00,58.80'); // 执行统计被对象工时刷新
r($burns[900002]) && p('estimate,left,consumed') && e('18,7,10');                      // 燃尽图计算包含对象工时
r($scopeAfterChange) && p() && e('13');                                               // 新增需求后燃尽范围故事点上升
r($edited) && p('consumed,left') && e('4.00,1.00');                                   // 编辑后 consumed/left 变化
r($deleted) && p('deleted') && e('1');                                                // 删除后记录软删除
r($taskAfter) && p() && e('0.00');                                                    // 核心任务表未被对象工时修改
r($deniedRecord) && p() && e('1');                                                    // 无登记权限的用户被拒绝
r($workerRecordAllowed) && p() && e('1');                                             // 有登记权限的用户可登记
r($workerEditDenied) && p() && e('1');                                                // 所有者无编辑权限时仍被拒绝
r($workerEditAllowed) && p() && e('1');                                               // 所有者有编辑权限时可操作
r($otherEditDenied) && p() && e('1');                                                 // 非所有者即使有编辑权限也被拒绝
r($closedExecutionSummary[900002]) && p('estimate,consumed,left') && e('12,11,4');     // 关闭对象后燃尽汇总清零预计和剩余但保留耗时
r($statsAfterClose) && p('left') && e('5.00');                                        // 关闭动作立即刷新项目剩余工时
r($burnAfterClose) && p('estimate,consumed,left') && e('12.00,11.00,4.00');            // 关闭动作立即刷新当日燃尽
#!/usr/bin/env php
<?php
/**
title=测试角色限制 - admin/worker/tester 在 qa-only transition 下的行为;
timeout=0
cid=0

- 执行$workerAllowed ? '1' : '0 @0
- 执行$testerAllowed ? '1' : '0 @1
- 执行$adminAllowed ? '1' : '0 @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->loadModel('statetransition');

/* Set up: qa-only transition testing → review → active */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$storyDef['statuses'][] = array('key'=>'testing','label'=>array('zh_cn'=>'测试','en'=>'Testing'),'category'=>'normal','color'=>'#999','isSystem'=>false,'isEntry'=>false,'fieldRules'=>array());
$storyDef['transitions'][] = array(
    'key'=>'testing-to-active-via-review',
    'fromStatus'=>'testing','toStatus'=>'active','action'=>'review','branch'=>null,
    'label'=>array('zh_cn'=>'测试','en'=>'Test'),
    'roles'=>array('qa'),
    'accounts'=>array(),
    'requireComment'=>false,'enabled'=>true,'isCustom'=>false,
    'buttonLabel'=>array('zh_cn'=>'','en'=>''),'buttonIcon'=>null,'buttonOrder'=>0,
    'buttonGroup'=>'primary','sideEffects'=>array(),'condition'=>null
);
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

/* Test worker (dev) - should be DENIED */
su('worker');
$workerAllowed = $tester->statetransition->isActionAllowed('story', 0, 'testing', 'review');

/* Test tester (qa) - should be ALLOWED */
su('tester');
$testerAllowed = $tester->statetransition->isActionAllowed('story', 0, 'testing', 'review');

/* Test admin - should BYPASS per ZenTao convention */
su('admin');
$adminAllowed = $tester->statetransition->isActionAllowed('story', 0, 'testing', 'review');

r($workerAllowed ? '1' : '0') && p() && e('0');
r($testerAllowed ? '1' : '0') && p() && e('1');
r($adminAllowed ? '1' : '0') && p() && e('1');
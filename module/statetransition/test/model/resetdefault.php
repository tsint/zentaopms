#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->resetToDefault();
timeout=0
cid=0

- 执行$summary @epic:0:1:5:9;requirement:0:1:5:9;story:0:1:5:9;bug:0:1:3:4;task:0:1:6:11
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester, $config;
$tester->loadModel('statetransition');

$config->URAndSR  = 1;
$config->enableER = 1;
$tester->app->loadConfig('statetransition');
$config->statetransition->objectTypes = array_values(array_unique(array_merge($config->statetransition->objectTypes, array('epic', 'requirement'))));
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

$objectTypes = array('epic', 'requirement', 'story', 'bug', 'task');
$summary = array();
foreach($objectTypes as $objectType)
{
    $default = $tester->statetransition->getDefaultDefinition($objectType);
    $custom  = $default;
    $custom['statuses'][] = array(
        'key'        => 'custom_reset_check',
        'label'      => array('zh_cn' => '恢复默认测试', 'en' => 'Reset check'),
        'category'   => 'normal',
        'color'      => '#1abc9c',
        'isSystem'   => false,
        'isEntry'    => true,
        'fieldRules' => new stdClass(),
    );
    $custom['entries'] = array('custom_reset_check');

    $tester->statetransition->saveDefinition($objectType, 0, $custom, 0, true);
    $customRow = $tester->statetransition->getDefinition($objectType, 0);
    $customIsDefault = $tester->statetransition->isDefaultDefinition($objectType, $customRow['definition']) ? '1' : '0';

    $tester->statetransition->resetToDefault($objectType, 0);
    $resetRow = $tester->statetransition->getDefinition($objectType, 0);
    $resetIsDefault = $tester->statetransition->isDefaultDefinition($objectType, $resetRow['definition']) ? '1' : '0';
    $summary[] = $objectType . ':' . $customIsDefault . ':' . $resetIsDefault . ':' . count($resetRow['definition']['statuses']) . ':' . count($resetRow['definition']['transitions']);
}

r(implode(';', $summary)) && p() && e('epic:0:1:5:9;requirement:0:1:5:9;story:0:1:5:9;bug:0:1:3:4;task:0:1:6:11');

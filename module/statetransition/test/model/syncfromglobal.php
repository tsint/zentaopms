#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->syncFromGlobal();
timeout=0
cid=0

- 执行$summary @epic:1:1:0:1;requirement:1:1:0:1;story:1:1:0:1;bug:1:1:0:1;task:1:1:0:1
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

$productID = 999301;
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->in('global,product')->exec();
$tester->statetransition->clearCache();

$makeDefinition = function(string $objectType, string $statusKey, string $statusLabel) use ($tester): array {
    $definition = $tester->statetransition->getDefaultDefinition($objectType);
    $definition['statuses'][] = array(
        'key'        => $statusKey,
        'label'      => array('zh_cn' => $statusLabel, 'en' => $statusLabel),
        'category'   => 'normal',
        'color'      => '#1abc9c',
        'isSystem'   => false,
        'isEntry'    => true,
        'fieldRules' => new stdClass(),
    );
    $definition['entries'] = array($statusKey);
    return $definition;
};

$summary = array();
foreach(array('epic', 'requirement', 'story', 'bug', 'task') as $objectType)
{
    $globalDefinition  = $makeDefinition($objectType, 'global_sync_check', '全局同步测试');
    $productDefinition = $makeDefinition($objectType, 'product_sync_check', '产品同步测试');

    $tester->statetransition->saveDefinition($objectType, 0, $globalDefinition, 0, true);
    $tester->statetransition->saveDefinition($objectType, $productID, $productDefinition, 0, true);

    $result     = $tester->statetransition->syncFromGlobal($objectType, $productID);
    $productRow = $tester->statetransition->getDefinition($objectType, $productID);

    $matchesGlobal = $productRow['definition'] == $tester->statetransition->getDefinition($objectType, 0)['definition'] ? '1' : '0';
    $hasGlobalOnly = in_array('global_sync_check', $productRow['definition']['entries'], true) ? '1' : '0';
    $hasProductOld = in_array('product_sync_check', $productRow['definition']['entries'], true) ? '1' : '0';
    $summary[] = $objectType . ':' . ($result['ok'] ? '1' : '0') . ':' . $matchesGlobal . ':' . $hasProductOld . ':' . $hasGlobalOnly;
}

r(implode(';', $summary)) && p() && e('epic:1:1:0:1;requirement:1:1:0:1;story:1:1:0:1;bug:1:1:0:1;task:1:1:0:1');

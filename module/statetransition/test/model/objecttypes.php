#!/usr/bin/env php
<?php
/**
title=测试 statetransition 配置按业务需求/用户需求开关过滤对象类型;
timeout=0
cid=0

- 执行$summary @off:story,bug,task;ur:requirement,story,bug,task;er:epic,requirement,story,bug,task
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';

global $config;

$loadObjectTypes = function(int $enableER, int $URAndSR) use ($config): string {
    $config->enableER = $enableER;
    $config->URAndSR  = $URAndSR;
    include dirname(__FILE__, 3) . '/config/config.php';
    return implode(',', $config->statetransition->objectTypes);
};

$summary = array();
$summary[] = 'off:' . $loadObjectTypes(0, 0);
$summary[] = 'ur:'  . $loadObjectTypes(0, 1);
$summary[] = 'er:'  . $loadObjectTypes(1, 1);

r(implode(';', $summary)) && p() && e('off:story,bug,task;ur:requirement,story,bug,task;er:epic,requirement,story,bug,task');

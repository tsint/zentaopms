#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel 修复历史数据中字面 \\uXXXX 转义序列问题;
timeout=0
cid=0

- 执行$repair1 @草稿
- 执行$repair2 @变更
- 执行$repair3 @测试标签
- 执行$repair4 @草稿
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

global $tester;
$tester->loadModel('statetransition');

/* The historical corruption stores literal "\uXXXX" escape sequences as text
   in label fields. After json_decode, the PHP string contains literal backslash-u-XXXX.
   We need to repair these to actual UTF-8 characters. */

/* Case 1: single-backslash literal '草稿' (12 chars in PHP, hex: 5c75383334395c7537613366) */
$corrupted1 = hex2bin('5c75383334395c7537613366');  // '草稿'
$repair1 = $tester->statetransition->repairLabelEscape($corrupted1);

/* Case 2: another label '变更' = 变更 */
$corrupted2 = hex2bin('5c75353364385c7536366634');  // '变更'
$repair2 = $tester->statetransition->repairLabelEscape($corrupted2);

/* Case 3: 4-char label '测试标签' = 测试标签 */
$corrupted3 = hex2bin('5c75366434625c75386264355c75363830375c7537623765');  // '测试标签'
$repair3 = $tester->statetransition->repairLabelEscape($corrupted3);

/* Case 4: real UTF-8 chars (should pass through unchanged) */
$repair4 = $tester->statetransition->repairLabelEscape('草稿');

r($repair1) && p() && e('草稿');
r($repair2) && p() && e('变更');
r($repair3) && p() && e('测试标签');
r($repair4) && p() && e('草稿');
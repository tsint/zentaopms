#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->normalizeDefinition();
timeout=0
cid=0

- 执行$result['ok'] @1
- 执行$statusCount @5
- 执行$transCount @9
- 执行$missedLabel @草稿
- 执行$missedColor @gray
- 执行$syncedEntries @missing
- 执行$status1IsEntryAfter @0
- 执行$legacyHasStat @1
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

$storyDef = $tester->statetransition->getDefaultDefinition('story');
$result = $tester->statetransition->normalizeDefinition($storyDef, 'story');

$missing = $storyDef;
$missing['statuses'][0]['label'] = array();
$missing['statuses'][0]['color'] = '';
$missingResult = $tester->statetransition->normalizeDefinition($missing, 'story');

$isEntrySync = $storyDef;
$isEntrySync['statuses'][1]['isEntry'] = true;
$isEntrySync['entries'] = array('draft');
$isEntryResult = $tester->statetransition->normalizeDefinition($isEntrySync, 'story');

$legacy = array(
    'schemaVersion' => 1,
    'nodes' => $storyDef['statuses'],
    'edges' => $storyDef['transitions'],
    'entries' => $storyDef['entries'],
);
$legacyResult = $tester->statetransition->normalizeDefinition($legacy, 'story');

$statusCount    = count($result['definition']['statuses']);
$transCount     = count($result['definition']['transitions']);
$missedLabel    = $missingResult['definition']['statuses'][0]['label']['zh_cn'];
$missedColor    = $missingResult['definition']['statuses'][0]['color'] === '#999999' ? 'gray' : 'other';
$syncedEntries  = in_array('reviewing', $isEntryResult['definition']['entries']) ? 'reviewing' : 'missing';
$legacyHasStat  = isset($legacyResult['definition']['statuses']) && !isset($legacyResult['definition']['nodes']) ? '1' : '0';

$status1IsEntryAfter = $isEntryResult['definition']['statuses'][1]['isEntry'] ? '1' : '0';

r($result['ok']) && p() && e('1');
r($statusCount) && p() && e('5');
r($transCount) && p() && e('9');
r($missedLabel) && p() && e('草稿');
r($missedColor) && p() && e('gray');
/* New behavior: entries[] is canonical. Input had entries=['draft'] and
   statuses[1].isEntry=true. normalize should KEEP entries=['draft'] (reviewing
   NOT added), and SYNC status.isEntry FROM entries (reviewing.isEntry becomes false). */
r($syncedEntries) && p() && e('missing');
r($status1IsEntryAfter) && p() && e('0');
r($legacyHasStat) && p() && e('1');
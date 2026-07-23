#!/usr/bin/env php
<?php
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');
global $tester;
$tester->loadModel('statetransition');

$overrides = $tester->statetransition->getActionLabelOverrides('story', 4, 'active');
echo "=== Overrides for story in active (product 4) ===\n";
foreach($overrides as $action => $label) {
    echo "  $action => '$label' (length=" . strlen($label) . ", bytes=" . bin2hex($label) . ")\n";
}

echo "\n=== Checking change action specifically ===\n";
$row = $tester->statetransition->getDefinition('story', 4);
if(empty($row))
{
    echo "No product 4 story workflow definition.\n";
    return;
}

foreach($row['definition']['transitions'] as $tr) {
    if($tr['fromStatus'] == 'active' && $tr['action'] == 'change') {
        echo "Label zh_cn: '" . $tr['label']['zh_cn'] . "'\n";
        echo "Label zh_cn bytes: " . bin2hex($tr['label']['zh_cn']) . "\n";
        echo "Label zh_cn json: " . json_encode($tr['label']) . "\n";
    }
}

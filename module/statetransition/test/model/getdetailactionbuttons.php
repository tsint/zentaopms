#!/usr/bin/env php
<?php
/**
title=测试 statetransitionModel->getDetailActionButtons();
timeout=0
cid=0

- 执行$noDefButtons @0
- 执行$noExistingButtons @1
- 执行$noExistingButtons[0]['url'], 'triggerCustom') !== false @1
- 执行$withExistingButtons @0
- 执行$roleDeniedButtons @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester;
$tester->loadModel('statetransition');

/* Only clear global scope to avoid wiping user's product-scope definitions. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Case 1: no definition → empty result. */
$noDefButtons = $tester->statetransition->getDetailActionButtons('story', 0, 1, 'active');

/* Set up a story definition with a custom status 'testing' and a transition testing→active via resolve. */
$storyDef = $tester->statetransition->getDefaultDefinition('story');
$storyDef['statuses'][] = array(
    'key' => 'testing',
    'label' => array('zh_cn' => '测试中', 'en' => 'Testing'),
    'category' => 'normal',
    'color' => '#f39c12',
    'isSystem' => false,
    'isEntry' => false,
    'fieldRules' => new stdClass(),
);
$storyDef['transitions'][] = array(
    'key' => 'testing-to-active-via-resolve',
    'fromStatus' => 'testing',
    'toStatus' => 'active',
    'action' => 'resolve',
    'branch' => null,
    'label' => array('zh_cn' => '解决', 'en' => 'Resolve'),
    'roles' => array(),
    'accounts' => array(),
    'requireComment' => false,
    'enabled' => true,
    'isCustom' => false,
    'buttonLabel' => array('zh_cn' => '解决', 'en' => 'Resolve'),
    'buttonIcon' => null,
    'buttonOrder' => 0,
    'buttonGroup' => 'primary',
    'sideEffects' => array(),
    'condition' => null,
);
$tester->statetransition->saveDefinition('story', 0, $storyDef, 0, true);

/* Case 2-4: testing→active via resolve button is injected when no existing 'resolve' action. */
$noExistingButtons = $tester->statetransition->getDetailActionButtons('story', 0, 1, 'testing', array());

/* Case 5: existing 'resolve' action → button is NOT injected (avoid duplicate). */
$withExistingButtons = $tester->statetransition->getDetailActionButtons('story', 0, 1, 'testing', array('resolve'));

/* Case 6: account restriction denies access for non-admin user. */
su('admin');
$storyDef2 = $tester->statetransition->getDefaultDefinition('story');
$storyDef2['statuses'][] = array(
    'key' => 'testing',
    'label' => array('zh_cn' => '测试中', 'en' => 'Testing'),
    'category' => 'normal',
    'color' => '#f39c12',
    'isSystem' => false,
    'isEntry' => false,
    'fieldRules' => new stdClass(),
);
$storyDef2['transitions'][] = array(
    'key' => 'testing-to-active-via-resolve',
    'fromStatus' => 'testing',
    'toStatus' => 'active',
    'action' => 'resolve',
    'branch' => null,
    'label' => array('zh_cn' => '解决', 'en' => 'Resolve'),
    'roles' => array(),
    'accounts' => array('admin'),
    'requireComment' => false,
    'enabled' => true,
    'isCustom' => false,
    'buttonLabel' => array('zh_cn' => '解决', 'en' => 'Resolve'),
    'buttonIcon' => null,
    'buttonOrder' => 0,
    'buttonGroup' => 'primary',
    'sideEffects' => array(),
    'condition' => null,
);
$tester->statetransition->saveDefinition('story', 0, $storyDef2, 0, true);
$tester->statetransition->clearCache();

su('worker');
$roleDeniedButtons = $tester->statetransition->getDetailActionButtons('story', 0, 1, 'testing', array());
su('admin'); /* restore */

r(count($noDefButtons)) && p() && e('0');
r(count($noExistingButtons)) && p() && e('1');
r(!empty($noExistingButtons) && strpos($noExistingButtons[0]['url'], 'triggerCustom') !== false) && p() && e('1');
r(count($withExistingButtons)) && p() && e('0');
r(count($roleDeniedButtons)) && p() && e('0');

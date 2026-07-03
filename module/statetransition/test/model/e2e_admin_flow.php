#!/usr/bin/env php
<?php
/**
title=端到端验证：模拟管理员通过 UI 配置和使用工作流的完整流程;
timeout=0
cid=0

- 执行$step1 @default
- 执行$step2 @1
- 执行$step3 @custom
- 执行$step4 @1
- 执行$step5 @closed
- 执行$step6 @closed
- 执行$step7 @default
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ob_start();

su('admin');
global $tester;
$tester->loadModel('statetransition');
$tester->loadModel('story');

/* Setup: clean state. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();

/* Ensure we have an active story to test with. */
$story = $tester->dao->select('*')->from(TABLE_STORY)->where('status')->eq('active')->andWhere('deleted')->eq('0')->andWhere('product')->eq(1)->limit(1)->fetch();
if(empty($story))
{
    $newStory = new stdclass();
    $newStory->product = 1;
    $newStory->type = 'story';
    $newStory->title = 'e2e-test-' . time();
    $newStory->status = 'active';
    $newStory->openedBy = 'admin';
    $newStory->openedDate = helper::now();
    $newStory->version = 1;
    $tester->dao->insert(TABLE_STORY)->data($newStory)->exec();
    $storyID = (int)$tester->dao->lastInsertID();
}
else
{
    $storyID = (int)$story->id;
}

/* Step 1: browse page - story uses default definition. */
$step1eff = $tester->statetransition->getDefinition('story', 0);
$step1 = ($step1eff === null) ? 'default' : 'custom';

/* Step 2: admin saves a custom definition with a custom button. */
$customDef = $tester->statetransition->getDefaultDefinition('story');
$customDef['transitions'][] = array(
    'key' => 'active-to-closed-via-custom_block',
    'fromStatus' => 'active',
    'toStatus' => 'closed',
    'action' => 'custom_block',
    'branch' => null,
    'label' => array('zh_cn' => '一键阻塞', 'en' => 'Block'),
    'roles' => array(),
    'accounts' => array(),
    'requireComment' => false,
    'enabled' => true,
    'isCustom' => true,
    'buttonLabel' => array('zh_cn' => '一键阻塞', 'en' => 'Block'),
    'buttonIcon' => 'ban',
    'buttonOrder' => 5,
    'buttonGroup' => 'danger',
    'sideEffects' => array(),
    'condition' => null,
);
$saveResult = $tester->statetransition->saveDefinition('story', 0, $customDef, 0, true);
$step2 = $saveResult['ok'] ? '1' : '0';

/* Step 3: browse page now shows custom. */
$tester->statetransition->clearCache();
$step3eff = $tester->statetransition->getDefinition('story', 0);
$step3 = ($step3eff === null) ? 'default' : 'custom';

/* Step 4: custom button is retrievable. */
$customButtons = $tester->statetransition->getCustomButtons('story', 0, 'active');
$step4 = count($customButtons) >= 1 ? '1' : '0';

/* Step 5: business method (story->close) uses workflow target. */
$tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();
$postData = new stdclass();
$postData->closedReason = 'done';
$postData->status = 'closed';
$_POST['comment'] = 'e2e workflow close';
$tester->story->close($storyID, $postData);
$fetched5 = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$step5 = $fetched5 ? $fetched5->status : 'MISSING';

/* Step 6: disable workflow - business method falls back. */
$tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();
$tester->statetransition->saveDefinition('story', 0, $customDef, 0, false);
dao::$errors = array();
$_POST = array();
$postData6 = new stdclass();
$postData6->closedReason = 'done';
$postData6->status = 'closed';
$_POST['comment'] = 'e2e disabled close';
$tester->story->close($storyID, $postData6);
$fetched6 = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
$step6 = $fetched6 ? $fetched6->status : 'MISSING';

/* Step 7: reset to default. */
$tester->statetransition->resetToDefault('story', 0);
$tester->statetransition->clearCache();
$step7eff = $tester->statetransition->getDefinition('story', 0);
$step7def = $step7eff['definition'];
$step7CustomCount = 0;
foreach($step7def['transitions'] as $tr)
{
    if(!empty($tr['isCustom'])) $step7CustomCount++;
}
$step7 = $step7CustomCount === 0 ? 'default' : 'custom';

/* Cleanup. */
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->statetransition->clearCache();
$tester->dao->update(TABLE_STORY)->set('status')->eq('active')->where('id')->eq($storyID)->exec();
ob_end_clean();

r($step1) && p() && e('default');
r($step2) && p() && e('1');
r($step3) && p() && e('custom');
r($step4) && p() && e('1');
r($step5) && p() && e('closed');
r($step6) && p() && e('closed');
r($step7) && p() && e('default');
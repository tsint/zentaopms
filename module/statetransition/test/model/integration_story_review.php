#!/usr/bin/env php
<?php
/**
title=测试需求评审应用自定义状态流转;
timeout=0
cid=0

- 执行$requirementStatus @verified
- 执行$epicStatus @verified
- 执行$storyFallbackStatus @active
- 执行$batchRequirementStatus @verified
- 执行$partialReviewStatus @reviewing
- 执行$finalReviewStatus @verified
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';

su('admin');

global $tester, $app, $config;
$tester->loadModel('statetransition');
$tester->loadModel('story');
$app->rawModule = 'story';
if(!isset($config->mail)) $config->mail = new stdclass();
$config->mail->turnon = false;

$productID = 999001;
$config->URAndSR  = 1;
$config->enableER = 1;
$tester->app->loadConfig('statetransition');
$config->statetransition->objectTypes = array_values(array_unique(array_merge($config->statetransition->objectTypes, array('epic', 'requirement'))));

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('product')->andWhere('productID')->eq($productID)->exec();
$tester->statetransition->clearCache();
dao::$errors = array();

$tester->dao->delete()->from(TABLE_PRODUCT)->where('id')->eq($productID)->exec();
$product = new stdclass();
$product->id          = $productID;
$product->name        = 'workflow review test product';
$product->code        = 'workflow-review-test';
$product->status      = 'normal';
$product->PO          = 'admin';
$product->acl         = 'open';
$product->createdBy   = 'admin';
$product->createdDate = helper::now();
$product->vision      = 'rnd';
$tester->dao->insert(TABLE_PRODUCT)->data($product)->exec();

$buildReviewDef = function(string $objectType) use ($tester) {
    $def = $tester->statetransition->getDefaultDefinition($objectType);
    $def['statuses'][] = array(
        'key'        => 'verified',
        'label'      => array('zh_cn' => '已确认', 'en' => 'Verified'),
        'category'   => 'normal',
        'color'      => '#1abc9c',
        'isSystem'   => false,
        'isEntry'    => false,
        'fieldRules' => new stdClass(),
    );
    foreach($def['transitions'] as $i => $transition)
    {
        if($transition['fromStatus'] === 'reviewing' && $transition['action'] === 'review' && $transition['branch'] === 'pass') $def['transitions'][$i]['toStatus'] = 'verified';
    }
    return $def;
};

$tester->statetransition->saveDefinition('requirement', 0, $buildReviewDef('requirement'), 0, true);
$tester->statetransition->saveDefinition('epic', 0, $buildReviewDef('epic'), 0, true);

$createReviewingStory = function(string $type, string $title, array $reviewers = array('admin')) use ($tester, $productID) {
    $story = new stdclass();
    $story->product     = $productID;
    $story->branch      = 0;
    $story->type        = $type;
    $story->title       = $title;
    $story->status      = 'reviewing';
    $story->stage       = 'wait';
    $story->openedBy    = 'admin';
    $story->openedDate  = helper::now();
    $story->assignedTo  = 'admin';
    $story->version     = 1;
    $story->reviewedBy  = '';
    $story->deleted     = '0';
    $tester->dao->insert(TABLE_STORY)->data($story)->exec();
    $storyID = (int)$tester->dao->lastInsertID();

    $spec = new stdclass();
    $spec->story   = $storyID;
    $spec->version = 1;
    $spec->title   = $title;
    $spec->spec    = '';
    $spec->verify  = '';
    $tester->dao->insert(TABLE_STORYSPEC)->data($spec)->exec();

    foreach($reviewers as $reviewer)
    {
        $review = new stdclass();
        $review->story    = $storyID;
        $review->version  = 1;
        $review->reviewer = $reviewer;
        $review->result   = '';
        $tester->dao->insert(TABLE_STORYREVIEW)->data($review)->exec();
    }

    return $storyID;
};

$review = function(int $storyID, string $account = 'admin') use ($tester) {
    su($account);
    $tester->statetransition->clearCache();
    $storyData = new stdclass();
    $storyData->result       = 'pass';
    $storyData->assignedTo   = 'admin';
    $storyData->closedReason = '';
    $storyData->pri          = '2';
    $_POST['comment'] = 'review pass';
    $tester->story->review($storyID, $storyData, 'review pass');
    return $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch('status');
};

$epicID        = $createReviewingStory('epic', 'workflow epic review');
$requirementID = $createReviewingStory('requirement', 'workflow requirement review');
$storyID       = $createReviewingStory('story', 'workflow story fallback review');
$batchRequirementID = $createReviewingStory('requirement', 'workflow batch requirement review');
$multiReviewRequirementID = $createReviewingStory('requirement', 'workflow multi reviewer requirement review', array('admin', 'developer1'));

$epicStatus        = $review($epicID);
$requirementStatus = $review($requirementID);
$storyFallbackStatus = $review($storyID);
$partialReviewStatus = $review($multiReviewRequirementID, 'admin');
$finalReviewStatus   = $review($multiReviewRequirementID, 'developer1');

su('admin');
$tester->statetransition->clearCache();
$tester->story->batchReview(array($batchRequirementID), 'pass');
$batchRequirementStatus = $tester->dao->select('status')->from(TABLE_STORY)->where('id')->eq($batchRequirementID)->fetch('status');

$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('global')->exec();
$tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)->where('scope')->eq('product')->andWhere('productID')->eq($productID)->exec();
$tester->statetransition->clearCache();

r($requirementStatus) && p() && e('verified');
r($epicStatus) && p() && e('verified');
r($storyFallbackStatus) && p() && e('active');
r($batchRequirementStatus) && p() && e('verified');
r($partialReviewStatus) && p() && e('reviewing');
r($finalReviewStatus) && p() && e('verified');

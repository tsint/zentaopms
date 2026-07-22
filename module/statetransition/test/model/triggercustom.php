#!/usr/bin/env php
<?php
/**
title=测试 statetransition::triggerCustom();
timeout=0
cid=0

- 执行$result1 @success
- 执行$updatedStory属性status @developing
- 执行$updatedStory属性assignedTo @developer1
- 执行$updatedStory属性lastEditedBy @admin
- 执行$result2 @success
- 执行$assignedStory属性status @active
- 执行$assignedStory属性assignedTo @developer2
- 执行$hasSqlError @0
*/
include dirname(__FILE__, 5) . '/test/lib/init.php';
include dirname(__FILE__, 2) . '/lib/model.class.php';
include dirname(__FILE__, 3) . '/control.php';

su('admin');

global $tester, $app;
$tester->loadModel('statetransition');

$productID = 1;
$backupDefinition = $tester->dao->select('*')->from(TABLE_WORKFLOW_DEFINITION)
    ->where('scope')->eq('product')
    ->andWhere('productID')->eq($productID)
    ->andWhere('objectType')->eq('story')
    ->fetch();

$storyID      = 0;
$response     = '';
$response2    = '';
$responseData = array('result' => 'fail');
$responseData2 = array('result' => 'fail');
$hasSqlError  = '0';

try
{
    $definition = $tester->statetransition->getDefaultDefinition('story');
    $definition['statuses'][] = array(
        'key'        => 'developing',
        'label'      => array('zh_cn' => '开发中', 'en' => 'Developing'),
        'category'   => 'normal',
        'color'      => '#3498db',
        'isSystem'   => false,
        'isEntry'    => false,
        'fieldRules' => new stdClass(),
    );
    $definition['transitions'][] = array(
        'key'            => 'active-to-developing-via-resolve',
        'fromStatus'     => 'active',
        'toStatus'       => 'developing',
        'action'         => 'resolve',
        'branch'         => null,
        'label'          => array('zh_cn' => '解决', 'en' => 'Resolve'),
        'roles'          => array(),
        'accounts'       => array(),
        'requireComment' => false,
        'enabled'        => true,
        'isCustom'       => false,
        'buttonLabel'    => array('zh_cn' => '解决', 'en' => 'Resolve'),
        'buttonIcon'     => null,
        'buttonOrder'    => 0,
        'buttonGroup'    => 'primary',
        'sideEffects'    => array(),
        'condition'      => null,
    );

    $tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)
        ->where('scope')->eq('product')
        ->andWhere('productID')->eq($productID)
        ->andWhere('objectType')->eq('story')
        ->exec();
    $tester->statetransition->clearCache();
    $tester->statetransition->saveDefinition('story', $productID, $definition, 0, true);

    $story = new stdclass();
    $story->product      = $productID;
    $story->branch       = 0;
    $story->module       = 0;
    $story->type         = 'story';
    $story->title        = 'trigger-custom-resolve-to-developing-' . time();
    $story->status       = 'active';
    $story->stage        = 'wait';
    $story->assignedTo   = 'admin';
    $story->openedBy     = 'admin';
    $story->openedDate   = helper::now();
    $story->version      = 1;
    $story->deleted      = '0';
    $tester->dao->insert(TABLE_STORY)->data($story)->exec();
    $storyID = (int)$tester->dao->lastInsertID();

    $_POST = array('comment' => '进入开发中', 'assignedTo' => 'developer1');
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $app->rawModule = 'statetransition';

    $controller = new statetransition('statetransition', 'triggerCustom');
    ob_start();
    try
    {
        $controller->triggerCustom('story', $storyID, 'active-to-developing-via-resolve');
    }
    catch(EndResponseException $e)
    {
        if(ob_get_level()) ob_end_clean();
        $response = $e->getContent();
    }
    if($response === '' && ob_get_level()) $response = ob_get_clean();

    $decoded = json_decode($response, true);
    if(is_array($decoded)) $responseData = $decoded;
    if(stripos($response, 'SQLSTATE') !== false || stripos($response, 'syntax error') !== false) $hasSqlError = '1';

    $updatedStory = $tester->dao->select('status,assignedTo,lastEditedBy')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();

    $tester->dao->update(TABLE_STORY)
        ->set('status')->eq('active')
        ->set('assignedTo')->eq('admin')
        ->where('id')->eq($storyID)
        ->exec();

    $_POST = array('comment' => '重新指派', 'assignedTo' => 'developer2');
    $controller = new statetransition('statetransition', 'triggerCustom');
    ob_start();
    try
    {
        $controller->triggerCustom('story', $storyID, 'active-to-active-via-assignTo');
    }
    catch(EndResponseException $e)
    {
        if(ob_get_level()) ob_end_clean();
        $response2 = $e->getContent();
    }
    if($response2 === '' && ob_get_level()) $response2 = ob_get_clean();

    $decoded2 = json_decode($response2, true);
    if(is_array($decoded2)) $responseData2 = $decoded2;
    if(stripos($response2, 'SQLSTATE') !== false || stripos($response2, 'syntax error') !== false) $hasSqlError = '1';

    $assignedStory = $tester->dao->select('status,assignedTo,lastEditedBy')->from(TABLE_STORY)->where('id')->eq($storyID)->fetch();
}
finally
{
    if($storyID) $tester->dao->delete()->from(TABLE_STORY)->where('id')->eq($storyID)->exec();

    $tester->dao->delete()->from(TABLE_WORKFLOW_DEFINITION)
        ->where('scope')->eq('product')
        ->andWhere('productID')->eq($productID)
        ->andWhere('objectType')->eq('story')
        ->exec();
    if($backupDefinition)
    {
        if($backupDefinition->createdDate === '') $backupDefinition->createdDate = null;
        if($backupDefinition->editedDate === '')  $backupDefinition->editedDate  = null;
        $tester->dao->replace(TABLE_WORKFLOW_DEFINITION)->data($backupDefinition)->exec();
    }
    $tester->statetransition->clearCache();

    $_POST = array();
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
}

$result1 = $responseData['result'] ?? 'fail';
$result2 = $responseData2['result'] ?? 'fail';

r($result1) && p() && e('success');
r($updatedStory) && p('status') && e('developing');
r($updatedStory) && p('assignedTo') && e('developer1');
r($updatedStory) && p('lastEditedBy') && e('admin');
r($result2) && p() && e('success');
r($assignedStory) && p('status') && e('active');
r($assignedStory) && p('assignedTo') && e('developer2');
r($hasSqlError) && p() && e('0');
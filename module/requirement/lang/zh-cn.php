<?php
global $app, $config;
$app->loadLang('story');
$lang->requirement = clone $lang->story;

foreach($lang->requirement as $key => $value)
{
    if(!is_string($value)) continue;
    if(strpos($value, $lang->SRCommon) !== false) $lang->requirement->$key = str_replace($lang->SRCommon, $lang->URCommon, $value);
}

$lang->requirement->common = $lang->URCommon;

/* Independent status list (decoupled from story): requirement owns its own status labels
   so changes to story's statusList never affect requirement. Content mirrors story for now. */
$lang->requirement->statusList = array();
$lang->requirement->statusList['']          = '';
$lang->requirement->statusList['draft']     = '草稿';
$lang->requirement->statusList['reviewing'] = '评审中';
$lang->requirement->statusList['active']    = '激活';
$lang->requirement->statusList['changing']  = '变更中';
$lang->requirement->statusList['closed']    = '已关闭';

$lang->requirement->stageList = array();
$lang->requirement->stageList[''] = '';
$lang->requirement->stageList['wait'] = '未开始';
if($config->edition == 'ipd')
{
    $lang->requirement->stageList['inroadmap'] = '已设路标';
    $lang->requirement->stageList['incharter'] = 'Charter立项';
}
$lang->requirement->stageList['planned']    = '已计划';
$lang->requirement->stageList['projected']  = '研发立项';
$lang->requirement->stageList['developing'] = '研发中';
$lang->requirement->stageList['delivering'] = '交付中';
$lang->requirement->stageList['delivered']  = '已交付';
$lang->requirement->stageList['closed']     = '已关闭';

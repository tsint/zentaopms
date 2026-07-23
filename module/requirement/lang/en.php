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
$lang->requirement->statusList['draft']     = 'Draft';
$lang->requirement->statusList['reviewing'] = 'Reviewing';
$lang->requirement->statusList['active']    = 'Active';
$lang->requirement->statusList['changing']  = 'Changing';
$lang->requirement->statusList['closed']    = 'Closed';

$lang->requirement->stageList = array();
$lang->requirement->stageList[''] = '';
$lang->requirement->stageList['wait'] = 'Not Started';
if($config->edition == 'ipd')
{
    $lang->requirement->stageList['inroadmap'] = 'In Roadmap';
    $lang->requirement->stageList['incharter'] = 'In Charter';
}
$lang->requirement->stageList['planned']    = 'Planned';
$lang->requirement->stageList['projected']  = 'Initiated';
$lang->requirement->stageList['developing'] = 'In Development';
$lang->requirement->stageList['delivering'] = 'In Delivery';
$lang->requirement->stageList['delivered']  = 'Delivered';
$lang->requirement->stageList['closed']     = 'Closed';

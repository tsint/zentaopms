<?php
if(!defined('TABLE_WORKFLOWFLOWCHART')) define('TABLE_WORKFLOWFLOWCHART', '`' . $config->db->prefix . 'workflowflowchart`');
$config->objectTables['workflowflowchart'] = TABLE_WORKFLOWFLOWCHART;

$config->workflowflowchart = new stdclass();
$config->workflowflowchart->objectTypes = array('epic', 'requirement', 'story', 'bug', 'task', 'testcase');
$config->workflowflowchart->actions = new stdclass();
$config->workflowflowchart->actions->bug = array('resolve', 'close', 'activate');
$config->workflowflowchart->actions->task = array('start', 'restart', 'pause', 'finish', 'close', 'cancel', 'activate');
$config->workflowflowchart->actions->story = array('submitreview', 'review', 'change', 'recallreview', 'recallchange', 'close', 'activate');
$config->workflowflowchart->actions->epic = $config->workflowflowchart->actions->story;
$config->workflowflowchart->actions->requirement = $config->workflowflowchart->actions->story;
$config->workflowflowchart->actions->testcase = array('submitreview', 'review', 'edit', 'block', 'investigate', 'activate');

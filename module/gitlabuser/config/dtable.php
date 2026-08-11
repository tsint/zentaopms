<?php
global $lang;

$config->gitlabuser->dtable = new stdclass();

$config->gitlabuser->dtable->fieldList['id']['title'] = 'ID';
$config->gitlabuser->dtable->fieldList['id']['name']  = 'id';
$config->gitlabuser->dtable->fieldList['id']['type']  = 'id';

$config->gitlabuser->dtable->fieldList['gitlabAccount']['title']    = $lang->gitlabuser->gitlabAccount;
$config->gitlabuser->dtable->fieldList['gitlabAccount']['name']     = 'gitlabAccount';
$config->gitlabuser->dtable->fieldList['gitlabAccount']['type']     = 'text';
$config->gitlabuser->dtable->fieldList['gitlabAccount']['sortType'] = true;
$config->gitlabuser->dtable->fieldList['gitlabAccount']['flex']     = 2;

$config->gitlabuser->dtable->fieldList['zentaoAccount']['title']    = $lang->gitlabuser->zentaoAccount;
$config->gitlabuser->dtable->fieldList['zentaoAccount']['name']     = 'zentaoAccount';
$config->gitlabuser->dtable->fieldList['zentaoAccount']['type']     = 'user';
$config->gitlabuser->dtable->fieldList['zentaoAccount']['sortType'] = true;
$config->gitlabuser->dtable->fieldList['zentaoAccount']['flex']     = 2;

$config->gitlabuser->dtable->fieldList['createdBy']['title']    = $lang->gitlabuser->createdBy;
$config->gitlabuser->dtable->fieldList['createdBy']['name']     = 'createdBy';
$config->gitlabuser->dtable->fieldList['createdBy']['type']     = 'user';
$config->gitlabuser->dtable->fieldList['createdBy']['sortType'] = true;

$config->gitlabuser->dtable->fieldList['createdDate']['title']    = $lang->gitlabuser->createdDate;
$config->gitlabuser->dtable->fieldList['createdDate']['name']     = 'createdDate';
$config->gitlabuser->dtable->fieldList['createdDate']['type']     = 'datetime';
$config->gitlabuser->dtable->fieldList['createdDate']['sortType'] = true;

$config->gitlabuser->actionList = array();
$config->gitlabuser->actionList['edit']['icon']        = 'edit';
$config->gitlabuser->actionList['edit']['text']        = $lang->gitlabuser->edit;
$config->gitlabuser->actionList['edit']['hint']        = $lang->gitlabuser->edit;
$config->gitlabuser->actionList['edit']['showText']    = true;
$config->gitlabuser->actionList['edit']['url']         = array('module' => 'gitlabuser', 'method' => 'edit', 'params' => 'id={id}');
$config->gitlabuser->actionList['edit']['data-toggle'] = 'modal';
$config->gitlabuser->actionList['edit']['data-size']   = 'sm';

$config->gitlabuser->actionList['delete']['icon']       = 'trash';
$config->gitlabuser->actionList['delete']['text']       = $lang->gitlabuser->delete;
$config->gitlabuser->actionList['delete']['hint']       = $lang->gitlabuser->delete;
$config->gitlabuser->actionList['delete']['ajaxSubmit'] = true;
$config->gitlabuser->actionList['delete']['url']        = array('module' => 'gitlabuser', 'method' => 'delete', 'params' => 'id={id}');

$config->gitlabuser->dtable->fieldList['actions']['name']     = 'actions';
$config->gitlabuser->dtable->fieldList['actions']['title']    = $lang->actions;
$config->gitlabuser->dtable->fieldList['actions']['type']     = 'actions';
$config->gitlabuser->dtable->fieldList['actions']['sortType'] = false;
$config->gitlabuser->dtable->fieldList['actions']['fixed']    = 'right';
$config->gitlabuser->dtable->fieldList['actions']['menu']     = array('edit', 'delete');
$config->gitlabuser->dtable->fieldList['actions']['list']     = $config->gitlabuser->actionList;

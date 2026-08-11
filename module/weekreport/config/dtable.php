<?php
global $lang;
$config->weekreport->dtable = new stdclass();

/* ===== browse 列表列定义 ===== */
$config->weekreport->dtable->browse = new stdclass();
$list = array();

$list['id']['title']    = $lang->idAB;
$list['id']['type']     = 'id';
$list['id']['sortType'] = 'desc';

$list['name']['title'] = $lang->weekreport->fileName;
$list['name']['type']  = 'title';
$list['name']['width'] = '240';
$list['name']['fixed'] = 'left';

$list['createdBy']['title'] = $lang->weekreport->importedBy;
$list['createdBy']['type']  = 'user';
$list['createdBy']['name']  = 'createdBy';

$list['createdDate']['title']    = $lang->weekreport->importedTime;
$list['createdDate']['type']     = 'datetime';
$list['createdDate']['sortType'] = true;

$list['actions']['title']    = $lang->actions;
$list['actions']['type']     = 'actions';
$list['actions']['width']    = '120';
$list['actions']['fixed']    = 'right';
$list['actions']['sortType'] = false;
$list['actions']['name']     = 'actions';
$list['actions']['menu']     = array('view', 'delete');

$list['actions']['list']['view']['icon']   = 'search';
$list['actions']['list']['view']['hint']   = $lang->weekreport->generateScreen;
$list['actions']['list']['view']['url']    = helper::createLink('weekreport', 'view', 'id={id}');
$list['actions']['list']['view']['target'] = '_blank';

$list['actions']['list']['delete']['icon']         = 'trash';
$list['actions']['list']['delete']['hint']         = $lang->delete;
$list['actions']['list']['delete']['url']          = helper::createLink('weekreport', 'delete', 'id={id}');
$list['actions']['list']['delete']['className']    = 'ajax-submit';
$list['actions']['list']['delete']['data-confirm'] = array('message' => $lang->weekreport->confirmDelete, 'icon' => 'icon-exclamation-sign', 'iconClass' => 'warning-pale rounded-full icon-2x');

$config->weekreport->dtable->browse->fieldList = $list;

<?php
$config->gitlabuser->form = new stdclass();
$config->gitlabuser->form->create['gitlabAccount'] = array('type' => 'string', 'required' => true,  'default' => '');
$config->gitlabuser->form->create['zentaoAccount'] = array('type' => 'string', 'required' => true,  'default' => '');
$config->gitlabuser->form->create['createdDate']   = array('type' => 'string', 'required' => false, 'default' => helper::now());

$config->gitlabuser->form->edit['gitlabAccount'] = array('type' => 'string', 'required' => true,  'default' => '');
$config->gitlabuser->form->edit['zentaoAccount'] = array('type' => 'string', 'required' => true,  'default' => '');
$config->gitlabuser->form->edit['editedDate']    = array('type' => 'string', 'required' => false, 'default' => helper::now());

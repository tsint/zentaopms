<?php
$config->objecteffort = new stdclass();
$config->objecteffort->form = new stdclass();

$config->objecteffort->form->record = array();
$config->objecteffort->form->record['execution'] = array('type' => 'int', 'required' => false, 'default' => 0);
$config->objecteffort->form->record['project']   = array('type' => 'int', 'required' => false, 'default' => 0);
$config->objecteffort->form->record['date']     = array('type' => 'date',   'required' => true);
$config->objecteffort->form->record['estimate'] = array('type' => 'float',  'required' => false, 'default' => 0);
$config->objecteffort->form->record['consumed'] = array('type' => 'float',  'required' => true);
$config->objecteffort->form->record['left']     = array('type' => 'string', 'required' => false, 'default' => '');
$config->objecteffort->form->record['work']     = array('type' => 'string', 'required' => false, 'default' => '');

$config->objecteffort->form->recordBatch = array();
$config->objecteffort->form->recordBatch['execution'] = array('type' => 'int', 'required' => false, 'default' => array());
$config->objecteffort->form->recordBatch['project']   = array('type' => 'int', 'required' => false, 'default' => array());
$config->objecteffort->form->recordBatch['date']     = array('type' => 'date',   'required' => false, 'default' => array(), 'base' => true);
$config->objecteffort->form->recordBatch['work']     = array('type' => 'string', 'required' => false, 'default' => array());
$config->objecteffort->form->recordBatch['consumed'] = array('type' => 'float',  'required' => false, 'default' => array());
$config->objecteffort->form->recordBatch['left']     = array('type' => 'string', 'required' => false, 'default' => array());

$config->objecteffort->form->edit = $config->objecteffort->form->record;

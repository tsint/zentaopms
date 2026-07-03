DROP TABLE IF EXISTS `zt_workflow_definition`;
DELETE FROM `zt_grouppriv` WHERE `module` = 'statetransition';
DELETE FROM `zt_action` WHERE `objectType` = 'statetransition';

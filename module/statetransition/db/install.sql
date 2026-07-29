CREATE TABLE IF NOT EXISTS `zt_workflow_definition` (
  `id`          mediumint unsigned NOT NULL AUTO_INCREMENT,
  `scope`       enum('global','product') NOT NULL DEFAULT 'global',
  `productID`   mediumint unsigned NOT NULL DEFAULT 0,
  `objectType`  varchar(30) NOT NULL,
  `name`        varchar(100) NOT NULL DEFAULT '',
  `enabled`     enum('0','1') NOT NULL DEFAULT '0',
  `version`     int unsigned NOT NULL DEFAULT 1,
  `definition`  mediumtext NOT NULL,
  `createdBy`   varchar(30) NOT NULL DEFAULT '',
  `createdDate` datetime DEFAULT NULL,
  `editedBy`    varchar(30) NOT NULL DEFAULT '',
  `editedDate`  datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scope_obj` (`scope`, `productID`, `objectType`),
  KEY `idx_obj_lookup` (`objectType`, `productID`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`) VALUES
(1, 'statetransition', 'browse'),
(1, 'statetransition', 'manage'),
(1, 'statetransition', 'reset'),
(1, 'statetransition', 'syncGlobal'),
(1, 'statetransition', 'toggle'),
(1, 'statetransition', 'triggerCustom');

CREATE TABLE IF NOT EXISTS `zt_workflowflowchart` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `objectType` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `enabled` enum('0','1') NOT NULL DEFAULT '0',
  `version` int unsigned NOT NULL DEFAULT 1,
  `definition` mediumtext NOT NULL,
  `createdBy` varchar(30) NOT NULL DEFAULT '',
  `createdDate` datetime DEFAULT NULL,
  `editedBy` varchar(30) NOT NULL DEFAULT '',
  `editedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectType` (`objectType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`) VALUES
(1, 'workflowflowchart', 'browse'),
(1, 'workflowflowchart', 'manage');

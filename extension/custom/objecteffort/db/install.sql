CREATE TABLE IF NOT EXISTS `zt_objecteffort` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `objectType` varchar(30) NOT NULL,
  `objectID` mediumint unsigned NOT NULL,
  `product` mediumint unsigned NOT NULL DEFAULT 0,
  `project` mediumint unsigned NOT NULL DEFAULT 0,
  `execution` mediumint unsigned NOT NULL DEFAULT 0,
  `account` varchar(30) NOT NULL,
  `date` date NOT NULL,
  `estimate` decimal(12,2) unsigned NOT NULL DEFAULT 0.00,
  `consumed` decimal(12,2) unsigned NOT NULL DEFAULT 0.00,
  `left` decimal(12,2) NOT NULL DEFAULT 0.00,
  `work` text NOT NULL,
  `createdBy` varchar(30) NOT NULL,
  `createdDate` datetime NOT NULL,
  `editedBy` varchar(30) NOT NULL DEFAULT '',
  `editedDate` datetime DEFAULT NULL,
  `deleted` enum('0','1') NOT NULL DEFAULT '0',
  `deletedBy` varchar(30) NOT NULL DEFAULT '',
  `deletedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object` (`objectType`, `objectID`, `deleted`),
  KEY `execution` (`execution`, `objectType`, `objectID`, `deleted`),
  KEY `project` (`project`, `deleted`),
  KEY `accountDate` (`account`, `date`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`) VALUES
(1, 'objecteffort', 'record'),
(1, 'objecteffort', 'edit'),
(1, 'objecteffort', 'delete');

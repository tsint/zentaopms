CREATE TABLE IF NOT EXISTS `zt_weekreport` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `pathname`    varchar(255) NOT NULL DEFAULT '' COMMENT '相对 www/data/upload/{cid}/ 的路径',
  `extension`   varchar(30)  NOT NULL DEFAULT '' COMMENT '扩展名 xls/xlsx',
  `size`        int unsigned NOT NULL DEFAULT 0  COMMENT '文件大小(字节)',
  `year`        smallint     NOT NULL DEFAULT 0  COMMENT '周报年份',
  `beginDate`   date         DEFAULT NULL       COMMENT '周报开始日期',
  `endDate`     date         DEFAULT NULL       COMMENT '周报结束日期',
  `recordCount` int unsigned NOT NULL DEFAULT 0  COMMENT '解析出的有效行数',
  `createdBy`   varchar(30)  NOT NULL DEFAULT '',
  `createdDate` datetime     DEFAULT NULL,
  `editedBy`    varchar(30)  NOT NULL DEFAULT '',
  `editedDate`  datetime     DEFAULT NULL,
  `deleted`     tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
CREATE INDEX `year`        ON `zt_weekreport`(`year`);
CREATE INDEX `createdDate` ON `zt_weekreport`(`createdDate`);

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'weekreport', 'browse' FROM `zt_grouppriv` WHERE `module` = 'report' AND `method` = 'globalEffort';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'weekreport', 'import' FROM `zt_grouppriv` WHERE `module` = 'report' AND `method` = 'globalEffort';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'weekreport', 'view' FROM `zt_grouppriv` WHERE `module` = 'report' AND `method` = 'globalEffort';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'weekreport', 'delete' FROM `zt_grouppriv` WHERE `module` = 'report' AND `method` = 'globalEffort';

CREATE TABLE IF NOT EXISTS `zt_gitlabuser` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `gitlabAccount` varchar(128) NOT NULL DEFAULT '' COMMENT 'GitLab用户名',
  `zentaoAccount` varchar(128) NOT NULL DEFAULT '' COMMENT '禅道账号',
  `createdBy`     varchar(30)  NOT NULL DEFAULT '',
  `createdDate`   datetime     DEFAULT NULL,
  `editedBy`      varchar(30)  NOT NULL DEFAULT '',
  `editedDate`    datetime     DEFAULT NULL,
  `deleted`       tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gitlabAccount` (`gitlabAccount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'gitlabuser', 'browse' FROM `zt_grouppriv` WHERE `module` = 'group' AND `method` = 'browse';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'gitlabuser', 'create' FROM `zt_grouppriv` WHERE `module` = 'group' AND `method` = 'browse';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'gitlabuser', 'edit' FROM `zt_grouppriv` WHERE `module` = 'group' AND `method` = 'browse';
REPLACE INTO `zt_grouppriv` (`group`, `module`, `method`)
SELECT `group`, 'gitlabuser', 'delete' FROM `zt_grouppriv` WHERE `module` = 'group' AND `method` = 'browse';

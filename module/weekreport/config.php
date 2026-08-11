<?php
/**
 * The config file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
$config->weekreport = new stdclass();

/* 上传限制。 Allowed upload extensions and max size(MB). */
$config->weekreport->allowedExtensions = array('xls', 'xlsx');
$config->weekreport->maxUploadSize     = 50;

/* 列表分页。 Records per page. */
$config->weekreport->recPerPage = 20;

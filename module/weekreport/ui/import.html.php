<?php
declare(strict_types=1);
/**
 * The import view file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
namespace zin;

formPanel
(
    set::title($title),
    formGroup
    (
        set::label($lang->weekreport->fileName),
        input(set::type('file'), set::name('file'), set::accept('.xls,.xlsx'))
    ),
    span(setClass('text-gray text-sm'), sprintf($lang->weekreport->uploadTip, $this->config->weekreport->maxUploadSize))
);

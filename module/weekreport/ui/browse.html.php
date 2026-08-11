<?php
declare(strict_types=1);
/**
 * The browse view file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
namespace zin;

$tableData = initTableData($records, $this->config->weekreport->dtable->browse->fieldList, $this->weekreport);
$canImport = common::hasPriv('weekreport', 'import');

/* 搜索栏（文件名称 + 导入时间，使用 zin datePicker 组件）。 */
div
(
    setID('weekreportSearchbar'),
    setClass('flex items-center gap-4 px-2 py-2 flex-wrap'),
    inputGroup
    (
        setClass('flex items-center gap-2'),
        $lang->weekreport->fileName,
        input(set::name('name'), set::value($name), set::placeholder($lang->weekreport->fileName))
    ),
    inputGroup
    (
        setClass('flex items-center gap-2'),
        $lang->weekreport->importedTime,
        datePicker(set::name('begin'), set::value($begin))
    ),
    btn(setClass('btn primary'), set::icon('search'), on::click('weekreportSearch'), $lang->search->common),
    btn(setClass('btn'), set::url(helper::createLink('weekreport', 'browse')), $lang->weekreport->reset)
);

toolbar
(
    $canImport ? btn
    (
        setClass('btn primary'),
        set::icon('upload'),
        set::url(helper::createLink('weekreport', 'import')),
        set('data-toggle', 'modal'),
        set('data-size', 'sm'),
        $lang->weekreport->import
    ) : null
);

dtable
(
    set::cols($this->config->weekreport->dtable->browse->fieldList),
    set::data($tableData),
    set::userMap($users),
    set::orderBy($orderBy),
    set::sortLink(createLink('weekreport', 'browse', "name={$name}&begin={$begin}&orderBy={name}_{sortType}&recTotal={$pager->recTotal}&recPerPage={$pager->recPerPage}&pageID={$pager->pageID}")),
    set::footPager(usePager()),
    set::emptyTip($lang->weekreport->empty),
    set::loadPartial()
);

render();

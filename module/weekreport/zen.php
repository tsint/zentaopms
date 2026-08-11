<?php
/**
 * The zen file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
class weekreportZen extends weekreport
{
    /**
     * 准备列表数据（转为索引数组）。
     * Prepare browse list data.
     *
     * @param  array $records
     * @access protected
     * @return array
     */
    protected function prepareBrowseList(array $records): array
    {
        return array_values($records);
    }

}

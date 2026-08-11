<?php
declare(strict_types=1);
/**
 * The tao file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
class weekreportTao extends weekreportModel
{
    /**
     * 按文件名称和导入时间获取周报列表。
     * Fetch weekreport list by name and import date.
     *
     * @param  string      $name
     * @param  string      $begin
     * @param  string      $orderBy
     * @param  object|null $pager
     * @access protected
     * @return array
     */
    protected function fetchList(string $name, string $begin, string $orderBy, object $pager = null): array
    {
        return $this->dao->select('*')->from(TABLE_WEEKREPORT)
            ->where('deleted')->eq(0)
            ->beginIF($name)->andWhere('name')->like("%{$name}%")->fi()
            ->beginIF($begin)
                ->andWhere('createdDate')->ge($begin . ' 00:00:00')
                ->andWhere('createdDate')->le($begin . ' 23:59:59')
            ->fi()
            ->orderBy($orderBy)
            ->page($pager)
            ->fetchAll('id');
    }

    /**
     * 按 id 获取周报记录。
     * Fetch a weekreport by id.
     *
     * @param  int $id
     * @access protected
     * @return object|false
     */
    protected function fetchRecord(int $id): object|false
    {
        return $this->dao->select('*')->from(TABLE_WEEKREPORT)
            ->where('id')->eq($id)
            ->andWhere('deleted')->eq(0)
            ->fetch();
    }
}

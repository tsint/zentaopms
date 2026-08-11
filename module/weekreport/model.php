<?php
/**
 * The model file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     weekreport
 * @link        https://www.zentao.net
 */
class weekreportModel extends model
{
    /** 标记表是否已检查，避免每次请求都执行 SHOW TABLES。 */
    private static bool $tableEnsured = false;

    /**
     * Construct.
     *
     * @param  string $appName
     * @access public
     * @return void
     */
    public function __construct(string $appName = '')
    {
        parent::__construct($appName);
        $this->ensureTable();
    }

    /**
     * 确保 zt_weekreport 表存在（多机部署/开发环境自动建表，避免手动执行 SQL）。
     * Ensure the zt_weekreport table exists (auto-create for multi-machine deploy).
     *
     * @access private
     * @return void
     */
    private function ensureTable(): void
    {
        if(self::$tableEnsured) return;
        self::$tableEnsured = true;

        $table = trim(TABLE_WEEKREPORT, '`');
        $exists = $this->dbh->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if($exists) return;

        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->dbh->exec("CREATE INDEX `year`        ON `{$table}`(`year`)");
        $this->dbh->exec("CREATE INDEX `createdDate` ON `{$table}`(`createdDate`)");
    }

    /**
     * 获取周报列表。
     * Get weekreport list by name and import date.
     *
     * @param  string $name
     * @param  string $begin
     * @param  string $orderBy
     * @param  object $pager
     * @access public
     * @return array
     */
    public function getList(string $name, string $begin, string $orderBy, object $pager = null): array
    {
        return $this->weekreportTao->fetchList($name, $begin, $orderBy, $pager);
    }

    /**
     * 获取单条周报记录。
     * Get a weekreport by id.
     *
     * @param  int    $id
     * @access public
     * @return object|false
     */
    public function getById(int $id): object|false
    {
        return $this->weekreportTao->fetchRecord($id);
    }

    /**
     * 按文件名获取未删除的周报记录（用于同名覆盖检测）。
     * Get a weekreport by name (for overwrite check).
     *
     * @param  string $name
     * @access public
     * @return object|false
     */
    public function getByName(string $name): object|false
    {
        return $this->dao->select('*')->from(TABLE_WEEKREPORT)
            ->where('name')->eq($name)
            ->andWhere('deleted')->eq(0)
            ->fetch();
    }

    /**
     * 创建周报记录。
     * Create a weekreport record.
     *
     * @param  array  $data
     * @access public
     * @return int
     */
    public function create(array $data): int
    {
        $now        = date('Y-m-d H:i:s');
        $weekreport = new stdclass();
        $weekreport->name        = $data['name'];
        $weekreport->pathname    = $data['pathname'];
        $weekreport->extension   = $data['extension'];
        $weekreport->size        = (int)$data['size'];
        $weekreport->year        = (int)$data['year'];
        $weekreport->beginDate   = $data['beginDate'];
        $weekreport->endDate     = $data['endDate'];
        $weekreport->recordCount = (int)$data['recordCount'];
        $weekreport->createdBy   = $this->app->user->account;
        $weekreport->createdDate = $now;

        $this->dao->insert(TABLE_WEEKREPORT)->data($weekreport)->autoCheck()->exec();
        return $this->dao->lastInsertID();
    }

    /**
     * 软删除周报记录。
     * Soft delete a weekreport record.
     *
     * @param  int    $id
     * @access public
     * @return bool
     */
    public function deleteByID(int $id): bool
    {
        $this->dao->update(TABLE_WEEKREPORT)->set('deleted')->eq(1)
            ->set('editedBy')->eq($this->app->user->account)
            ->set('editedDate')->eq(date('Y-m-d H:i:s'))
            ->where('id')->eq($id)->exec();
        return !dao::isError();
    }

    /**
     * 更新周报记录（同名覆盖时用）。
     * Update a weekreport record (for overwrite on duplicate name).
     *
     * @param  int    $id
     * @param  array  $data
     * @access public
     * @return bool
     */
    public function updateByID(int $id, array $data): bool
    {
        $this->dao->update(TABLE_WEEKREPORT)
            ->set('pathname')->eq($data['pathname'])
            ->set('extension')->eq($data['extension'])
            ->set('size')->eq((int)$data['size'])
            ->set('year')->eq((int)$data['year'])
            ->set('beginDate')->eq($data['beginDate'])
            ->set('endDate')->eq($data['endDate'])
            ->set('recordCount')->eq((int)$data['recordCount'])
            ->set('createdDate')->eq(date('Y-m-d H:i:s'))
            ->set('editedBy')->eq($this->app->user->account)
            ->set('editedDate')->eq(date('Y-m-d H:i:s'))
            ->where('id')->eq($id)
            ->exec();
        return !dao::isError();
    }

    /**
     * 读取 xls 全部行（含表头）。
     * Read all rows from a xls file (first row is header).
     *
     * @param  string $absolutePath
     * @access public
     * @return array
     */
    public function readExcelRows(string $absolutePath): array
    {
        if(!is_file($absolutePath)) return array();

        $this->app->loadClass('phpexcel', true);
        if(!phpExcel::canRead($absolutePath)) return array();

        $spreadsheet = phpExcel::load($absolutePath);
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    }

    /**
     * 由原始行推导年份/起止日期/记录数（不限模板列，扫描含日期格式的单元格）。
     * Derive year/beginDate/endDate/recordCount from raw rows (scan date-like cells).
     *
     * @param  array $rows
     * @access public
     * @return array
     */
    public function deriveMeta(array $rows): array
    {
        $dataRows = $rows ? array_slice($rows, 1) : array();
        $dates    = array();
        foreach($dataRows as $row)
        {
            foreach($row as $cell)
            {
                $val = trim((string)$cell);
                if($val === '' || !preg_match('/^\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}/', $val)) continue;
                $ts = strtotime($val);
                if($ts) $dates[] = $ts;
            }
        }

        $min = $dates ? min($dates) : 0;
        $max = $dates ? max($dates) : 0;
        return array(
            'year'        => $min ? (int)date('Y', $min) : (int)date('Y'),
            'beginDate'   => $min ? date('Y-m-d', $min) : null,
            'endDate'     => $max ? date('Y-m-d', $max) : null,
            'recordCount' => count($dataRows),
        );
    }

    /**
     * 获取文件在服务器上的绝对路径。
     * Get the absolute real path of the stored xls file.
     *
     * @param  object $record
     * @access public
     * @return string
     */
    public function getRealPath(object $record): string
    {
        $companyID = isset($this->app->company->id) ? $this->app->company->id : 1;
        return $this->app->getAppRoot() . "www/data/upload/{$companyID}/" . $record->pathname;
    }

    /**
     * 判断物理文件是否存在。
     * Check if the physical file exists.
     *
     * @param  object $record
     * @access public
     * @return bool
     */
    public function fileExists(object $record): bool
    {
        return is_file($this->getRealPath($record));
    }

    /**
     * 删除物理文件。
     * Unlink the physical file.
     *
     * @param  object $record
     * @access public
     * @return void
     */
    public function unlinkFile(object $record): void
    {
        $path = $this->getRealPath($record);
        if(is_file($path)) @unlink($path);
    }
}

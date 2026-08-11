<?php
declare(strict_types=1);
/**
 * The model file of gitlabuser module of ZenTaoPMS.
 *
 * 维护「GitLab 用户名 → 禅道账号」映射表。
 * 既供后台管理（CRUD），也供 worklogs 接口做 account 自动映射（getByGitlabAccount）。
 *
 * @package    gitlabuser
 */
class gitlabuserModel extends model
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
     * 确保 zt_gitlabuser 表存在（多机部署/开发环境自动建表，避免手动执行 SQL）。
     * Ensure the zt_gitlabuser table exists (auto-create for multi-machine deploy).
     *
     * @access private
     * @return void
     */
    private function ensureTable(): void
    {
        if(self::$tableEnsured) return;
        self::$tableEnsured = true;

        $table = trim(TABLE_GITLABUSER, '`');
        $exists = $this->dbh->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if($exists) return;

        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * 获取全部映射记录（未删除）。
     * Get all gitlab user mappings.
     *
     * @param  string $orderBy
     * @access public
     * @return array
     */
    public function getList(string $orderBy = 'id_desc'): array
    {
        return $this->dao->select('*')->from(TABLE_GITLABUSER)
            ->where('deleted')->eq('0')
            ->orderBy($orderBy)
            ->fetchAll('id');
    }

    /**
     * 检查 GitLab 用户名是否已存在映射。
     * Check whether gitlabAccount already has a mapping.
     *
     * @param  string $gitlabAccount
     * @param  int    $excludeID  排除自身（编辑时）
     * @access public
     * @return bool
     */
    public function isGitlabAccountExists(string $gitlabAccount, int $excludeID = 0): bool
    {
        $record = $this->dao->select('id')->from(TABLE_GITLABUSER)
            ->where('deleted')->eq('0')
            ->andWhere('gitlabAccount')->eq($gitlabAccount)
            ->beginIF($excludeID)->andWhere('id')->ne($excludeID)->fi()
            ->fetch();
        return !empty($record);
    }

    /**
     * 新增一条映射。
     * Create a gitlab user mapping.
     *
     * @param  object $data
     * @access public
     * @return int|false
     */
    public function create(object $data)
    {
        $this->dao->insert(TABLE_GITLABUSER)->data($data)->autoCheck()
            ->batchCheck($this->config->gitlabuser->create->requiredFields, 'notempty')
            ->exec();

        if(dao::isError()) return false;
        return $this->dao->lastInsertID();
    }

    /**
     * 更新一条映射。
     * Update a gitlab user mapping.
     *
     * @param  int    $id
     * @param  object $data
     * @access public
     * @return array|false
     */
    public function update(int $id, object $data): array|false
    {
        $old = $this->fetchByID($id);
        if(!$old) return false;

        $this->dao->update(TABLE_GITLABUSER)->data($data)->autoCheck()
            ->batchCheck($this->config->gitlabuser->edit->requiredFields, 'notempty')
            ->where('id')->eq($id)
            ->exec();

        if(dao::isError()) return false;
        return common::createChanges($old, $data);
    }

    /**
     * 按 GitLab 用户名查禅道账号（供 worklogs 接口映射用）。
     * Map a gitlab username to a zentao account. Returns '' if no mapping.
     *
     * @param  string $gitlabAccount
     * @access public
     * @return string
     */
    public function getByGitlabAccount(string $gitlabAccount): string
    {
        if($gitlabAccount === '') return '';
        $record = $this->dao->select('zentaoAccount')->from(TABLE_GITLABUSER)
            ->where('deleted')->eq('0')
            ->andWhere('gitlabAccount')->eq($gitlabAccount)
            ->fetch();
        return $record ? (string)$record->zentaoAccount : '';
    }
}

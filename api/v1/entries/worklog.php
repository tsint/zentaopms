<?php
/**
 * The worklog batch entry point of ZenTaoPMS (for GitLab CI).
 *
 * 批量登记工时接口：AK/SK 直传认证（内网，无签名），事务式提交（整批成功或整批回滚）。
 * 配置见 config/worklog.php，设计见 worklogs-api-design.md。
 *
 * @package entries
 */
class worklogEntry extends baseEntry
{
    /**
     * 构造方法：解析请求体、补 dao、加载配置、AK/SK 认证。
     * 注意必须继承 baseEntry（不是 entry），绕开会话登录检查。
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        if($this->app->action == 'options') return;

        $this->dao = $this->loadModel('common')->dao;
        $this->loadWorklogConfig();
        $this->authenticate();
    }

    /**
     * 加载 config/worklog.php（该文件不在主配置的自动加载列表内，这里显式引入）。
     *
     * @access private
     * @return void
     */
    private function loadWorklogConfig(): void
    {
        if(isset($this->config->worklog)) return;

        $config     = $this->config;
        $configFile = $this->app->configRoot . 'worklog.php';
        if(file_exists($configFile)) include $configFile;
    }

    /**
     * AK/SK 直传认证：比对 config 中的密钥，通过后注入服务账号身份。
     *
     * @access private
     * @return void
     */
    private function authenticate(): void
    {
        $worklogConfig = isset($this->config->worklog) ? $this->config->worklog : null;
        if(!$worklogConfig || empty($worklogConfig->accessKey) || empty($worklogConfig->secretKey)) $this->deny('服务未配置');

        $ak = isset($_SERVER['HTTP_X_AK']) ? (string)$_SERVER['HTTP_X_AK'] : '';
        $sk = isset($_SERVER['HTTP_X_SK']) ? (string)$_SERVER['HTTP_X_SK'] : '';
        if($ak === '' || $sk === '') $this->deny('缺少认证参数');

        /* 常量时间比对，且失败不区分 AK/SK，避免被枚举。*/
        if(!hash_equals((string)$worklogConfig->accessKey, $ak)) $this->deny('认证失败');
        if(!hash_equals((string)$worklogConfig->secretKey, $sk)) $this->deny('认证失败');

        /* 可选：来源 IP 白名单。*/
        $allowed = trim((string)(isset($worklogConfig->allowedIPs) ? $worklogConfig->allowedIPs : ''));
        if($allowed !== '')
        {
            $ips = array_map('trim', explode(',', $allowed));
            if(!in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', $ips, true)) $this->deny('来源 IP 不允许');
        }

        /* 认证通过 → 注入服务账号身份，权限走服务账号，工时归属走每条的 account，二者分离。*/
        $account   = isset($worklogConfig->serviceAccount) ? trim((string)$worklogConfig->serviceAccount) : '';
        $userModel = $this->loadModel('user');
        $user      = $account !== '' ? $userModel->getById($account) : false;
        if(!$user || !empty($user->deleted)) $this->deny('服务账号不可用');

        $user->admin     = strpos($this->app->company->admins, ",{$account},") !== false;
        $user->rights    = $userModel->authorize($account); /* 加载分组权限（同登录流程），hasPriv 检查依赖它。*/
        $this->app->user = $user;
    }

    /**
     * 认证失败，统一 401。
     *
     * @param  string $msg
     * @access private
     * @return void
     */
    private function deny(string $msg): void
    {
        throw EndResponseException::create($this->sendError(401, $msg));
    }

    /**
     * POST 登记工时：两遍扫描——先全量校验，再事务写入。
     *
     * 支持两种传参：
     *   objectID 为 int  → 单条写入，consumed 全额记到该对象
     *   objectID 为数组  → 多条写入，每个对象都记完整 consumed（不平分）
     *
     * @access public
     * @return string
     */
    public function post()
    {
        $worklog = $this->requestBody;
        if(!$worklog || !is_object($worklog)) return $this->send400('参数不能为空');

        /* 基础字段校验（不依赖 objectID 类型）。*/
        $objectType = isset($worklog->objectType) ? strtolower(trim((string)$worklog->objectType)) : '';
        $account    = isset($worklog->account)    ? trim((string)$worklog->account) : '';
        $consumed   = isset($worklog->consumed)   ? $worklog->consumed : null;
        $work       = isset($worklog->work)       ? trim((string)$worklog->work) : '';

        if(!in_array($objectType, array('task', 'story', 'bug'), true)) return $this->send400('objectType 非法（仅支持 task/story/bug）');
        if($account === '')  return $this->send400('account 不能为空');

        /* account 若为 GitLab 用户名，按 zt_gitlabuser 映射表转成禅道账号；查不到则按原值，兼容直接传禅道账号。*/
        $account = $this->resolveAccount($account);
        $worklog->account = $account;

        if($work === '')     return $this->send400('work 不能为空');
        if(!isset($worklog->objectID)) return $this->send400('objectID 不能为空');
        if($consumed === null || !is_numeric($consumed) || (float)$consumed <= 0) return $this->send400('consumed 必须大于 0');

        /* 将 objectID 标准化为 items 数组（objectID 为数组时，每个对象都记完整 consumed）。*/
        $items = $this->normalizeItem($worklog);
        if(empty($items)) return $this->send400('objectID 格式非法');

        $userModel         = $this->loadModel('user');
        $taskModel         = $this->loadModel('task');
        $objecteffortModel = $this->loadModel('objecteffort');

        /* ====== 第一遍：全量校验（不写库，一次性返回所有错误）====== */
        $errors = array();
        $index  = 0;
        foreach($items as $item)
        {
            $index ++;
            $error = $this->validateItem($item, $userModel, $taskModel, $objecteffortModel);
            if($error !== '') $errors[] = "第{$index}条失败: {$error}";
        }
        if(!empty($errors)) return $this->send400(implode("\n", $errors));

        /* ====== 第二遍：事务写入（全部通过才进入）。====== */
        $created = array();
        $this->dao->begin();

        $index = 0;
        foreach($items as $item)
        {
            $index ++;
            $error = $this->recordOne($item, $userModel, $taskModel, $objecteffortModel);
            if($error !== '')
            {
                $this->dao->rollBack();
                return $this->send400("第{$index}条写入异常: {$error}");
            }

            $created[] = array
            (
                'index'      => $index - 1,
                'objectType' => $objectType,
                'objectID'   => (int)$item->objectID,
                'account'    => $account,
                'consumed'   => round((float)$item->consumed, 2)
            );
        }

        $this->dao->commit();

        return $this->send(200, array('message' => '登记成功', 'created' => count($created), 'items' => $created));
    }

    /**
     * 把 account 按 GitLab 用户名映射表转成禅道账号。
     * 查不到映射时原样返回（兼容 CI 直接传禅道账号的老用法）。
     *
     * @access private
     * @param  string $account
     * @return string
     */
    private function resolveAccount(string $account): string
    {
        if($account === '') return '';
        $mapped = $this->loadModel('gitlabuser')->getByGitlabAccount($account);
        return $mapped !== '' ? $mapped : $account;
    }

    /**
     * 校验单条工时参数（不写库），返回错误信息或空串。
     *
     * @param  object $item
     * @param  object $userModel
     * @param  object $taskModel
     * @param  object $objecteffortModel
     * @access private
     * @return string 空串表示通过。
     */
    private function validateItem(object $item, object $userModel, object $taskModel, object $objecteffortModel): string
    {
        $objectType = isset($item->objectType) ? strtolower(trim((string)$item->objectType)) : '';
        $objectID   = isset($item->objectID)   ? (int)$item->objectID : 0;
        $account    = isset($item->account)    ? trim((string)$item->account) : '';
        $date       = isset($item->date) && $item->date ? trim((string)$item->date) : helper::today();

        $typeLabel = $objectType === 'task' ? '任务' : ($objectType === 'story' ? '需求' : 'Bug');

        if(!in_array($objectType, array('task', 'story', 'bug'), true)) return 'objectType 非法';
        if($objectID <= 0)   return "{$typeLabel} #{$objectID}: objectID 非法";
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) return "{$typeLabel} #{$objectID}: date 格式非法";
        if($date > helper::today()) return "{$typeLabel} #{$objectID}: date 不能晚于今天";
        if(isset($item->left) && $item->left !== '' && (!is_numeric($item->left) || (float)$item->left < 0)) return "{$typeLabel} #{$objectID}: left 非法";

        $targetUser = $userModel->getById($account);
        if(!$targetUser || !empty($targetUser->deleted)) return "{$typeLabel} #{$objectID}: 账号 {$account} 不存在";

        if($objectType == 'task')
        {
            $task = $this->dao->select('id,status')->from(TABLE_TASK)->where('id')->eq($objectID)->andWhere('deleted')->eq('0')->fetch();
            if(!$task) return "{$typeLabel} #{$objectID}: 不存在";
            if($task->status == 'closed') return "{$typeLabel} #{$objectID}: 已关闭";
        }
        else
        {
            $table  = $objectType === 'story' ? TABLE_STORY : TABLE_BUG;
            $object = $this->dao->select('id,status,deleted')->from($table)->where('id')->eq($objectID)->fetch();
            if(!$object || !empty($object->deleted)) return "{$typeLabel} #{$objectID}: 不存在或已删除";
            if(isset($object->status) && $object->status == 'closed') return "{$typeLabel} #{$objectID}: 已关闭";
        }

        return '';
    }

    /**
     * 将单条请求标准化为 items 数组。
     *
     * objectID 为 int   → 返回单元素数组，consumed 不变。
     * objectID 为 array → 返回 N 个元素，每个元素都记完整 consumed（不平分）。
     *
     * @param  object $worklog
     * @access private
     * @return array
     */
    private function normalizeItem(object $worklog): array
    {
        $objectID = isset($worklog->objectID) ? $worklog->objectID : null;
        $consumed = isset($worklog->consumed) ? (float)$worklog->consumed : 0;
        if($consumed <= 0) return array();

        /* objectID 为整数 → 单条。*/
        if(is_int($objectID) || (is_string($objectID) && ctype_digit($objectID)))
        {
            return array((object)array(
                'objectType' => $worklog->objectType,
                'objectID'   => (int)$objectID,
                'account'    => $worklog->account,
                'consumed'   => $consumed,
                'work'       => $worklog->work,
                'date'       => isset($worklog->date) ? $worklog->date : '',
                'left'       => isset($worklog->left) ? $worklog->left : '',
                'execution'  => isset($worklog->execution) ? $worklog->execution : 0,
            ));
        }

        /* objectID 为数组 → 每个对象都记完整 consumed（不平分）。*/
        if(is_array($objectID))
        {
            if(count($objectID) === 0) return array();
            $items = array();
            foreach($objectID as $id)
            {
                $items[] = (object)array(
                    'objectType' => $worklog->objectType,
                    'objectID'   => (int)$id,
                    'account'    => $worklog->account,
                    'consumed'   => $consumed,
                    'work'       => $worklog->work,
                    'date'       => isset($worklog->date) ? $worklog->date : '',
                    'left'       => isset($worklog->left) ? $worklog->left : '',
                    'execution'  => isset($worklog->execution) ? $worklog->execution : 0,
                );
            }
            return $items;
        }

        return array();
    }

    /**
     * 写入单条工时（校验已在第一遍完成，此处只负责写库）。
     *
     * @param  object $item
     * @param  object $userModel
     * @param  object $taskModel
     * @param  object $objecteffortModel
     * @access private
     * @return string 空串表示成功，否则为失败原因。
     */
    private function recordOne(object $item, object $userModel, object $taskModel, object $objecteffortModel): string
    {
        $objectType = strtolower(trim((string)$item->objectType));
        $objectID   = (int)$item->objectID;
        $account    = trim((string)$item->account);
        $work       = trim((string)$item->work);
        $date       = isset($item->date) && $item->date ? trim((string)$item->date) : helper::today();
        $typeLabel  = $objectType === 'task' ? '任务' : ($objectType === 'story' ? '需求' : 'Bug');

        $targetUser = $userModel->getById($account);

        if($objectType == 'task')
        {
            $error = $this->recordTaskEffort($taskModel, $objectID, $targetUser, $item, $date, $work);
        }
        else
        {
            $error = $this->recordObjectEffort($objecteffortModel, $objectType, $objectID, $account, $item, $date, $work);
        }

        return $error !== '' ? "{$typeLabel} #{$objectID}: {$error}" : '';
    }

    /**
     * 登记 task 工时（写 zt_effort，归属取 app->user->account，需临时切到目标用户）。
     *
     * 说明：直接调 taskModel->recordWorkhour 而非走 controller——controller 的
     * form::batchData 校验失败会抛 EndResponseException 中断批量流程，而 model 层
     * 用 dao::$errors 报校验错误，且写入链上无隐式 commit，天然适配事务回滚。
     *
     * @param  object $taskModel
     * @param  int    $taskID
     * @param  object $targetUser
     * @param  object $item
     * @param  string $date
     * @param  string $work
     * @access private
     * @return string 空串表示成功，否则为失败原因。
     */
    private function recordTaskEffort(object $taskModel, int $taskID, object $targetUser, object $item, string $date, string $work): string
    {
        $task = $this->dao->select('id,status')->from(TABLE_TASK)->where('id')->eq($taskID)->andWhere('deleted')->eq('0')->fetch();
        if(!$task) return '不存在';
        if($task->status == 'closed') return '已关闭';

        $record = new stdclass();
        $record->date     = $date;
        $record->consumed = (float)$item->consumed;
        $record->left     = isset($item->left) && $item->left !== '' ? $item->left : ''; /* 空串由 checkWorkhour 自动折算。*/
        $record->work     = $work;

        /* 临时切到目标用户，带上服务账号的管理员标志避免权限误判，写完立即恢复。*/
        $origUser          = $this->app->user;
        $targetUser->admin = isset($origUser->admin) ? $origUser->admin : false;
        $this->app->user   = $targetUser;

        $changes = $taskModel->recordWorkhour($taskID, array($record));

        $this->app->user = $origUser;

        if(dao::isError()) return $this->formatErrors(dao::getError());
        if(empty($changes)) return "当前登录用户 {$targetUser->account} 不在该多人任务的团队中";

        return '';
    }

    /**
     * 登记 story/bug 工时（写 zt_objecteffort，record 原生支持 data->account，不用切用户）。
     *
     * @param  object $objecteffortModel
     * @param  string $objectType
     * @param  int    $objectID
     * @param  string $account
     * @param  object $item
     * @param  string $date
     * @param  string $work
     * @access private
     * @return string 空串表示成功，否则为失败原因。
     */
    private function recordObjectEffort(object $objecteffortModel, string $objectType, int $objectID, string $account, object $item, string $date, string $work): string
    {
        $data = new stdclass();
        $data->account  = $account;
        $data->date     = $date;
        $data->consumed = (float)$item->consumed;
        $data->work     = $work;
        if(isset($item->left) && $item->left !== '')              $data->left      = (float)$item->left;
        if(isset($item->execution) && (int)$item->execution > 0)  $data->execution = (int)$item->execution;

        $effortID = $objecteffortModel->record($objectType, $objectID, $data);
        if(!$effortID)
        {
            if(dao::isError()) return $this->formatErrors(dao::getError());
            return '登记失败';
        }

        return '';
    }

    /**
     * 把 dao 错误数组压平成一行文本。
     *
     * @param  array $errors
     * @access private
     * @return string
     */
    private function formatErrors(array $errors): string
    {
        $messages = array();
        foreach($errors as $message)
        {
            if(is_array($message)) $message = implode('，', $message);
            $messages[] = trim((string)$message);
        }

        return implode('；', array_filter($messages));
    }
}

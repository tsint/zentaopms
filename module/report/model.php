 <?php
/**
 * The model file of report module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2023 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.cnezsoft.com)
 * @license     ZPL(http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     report
 * @version     $Id: model.php 4726 2013-05-03 05:51:27Z chencongzhi520@gmail.com $
 * @link        https://www.zentao.net
 */
?>
<?php
class reportModel extends model
{
    /**
     * 构造函数。
     * Construct.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->loadBIDAO();
    }

    /**
     * 计算每项数据的百分比。
     * Compute percent of every item.
     *
     * @param  array  $datas
     * @access public
     * @return array
     */
    public function computePercent(array $datas): array
    {
        /* Get data total. */
        $sum = 0;
        foreach($datas as $data) $sum += $data->value;

        /* Compute percent, and get total percent. */
        $totalPercent = 0;
        foreach($datas as $i => $data)
        {
            $data->percent = $data->value ? round($data->value / $sum, 4) : 0;
            $totalPercent += $data->percent;
        }
        if(isset($i)) $datas[$i]->percent = round(1 - $totalPercent + $datas[$i]->percent, 4);
        return $datas;
    }

    /**
     * 创建单个图表的 json 数据。
     * Create json data of single charts
     *
     * @param  array $sets
     * @param  array $dateList
     * @return array
     */
    public function createSingleJSON(array $sets, array $dateList): array
    {
        $preValue = 0;
        $data     = array();
        $now      = date('Y-m-d');
        $setsDate = array_keys($sets);
        foreach($dateList as $date)
        {
            $date = date('Y-m-d', strtotime($date));
            if($date > $now) break;

            if(!isset($sets[$date]) && $sets)
            {
                $tmpDate = $setsDate;
                $tmpDate[] = $date;
                sort($tmpDate);
                $tmpDateStr = ',' . join(',', $tmpDate);
                $preDate = rtrim(substr($tmpDateStr, 0, strpos($tmpDateStr, $date)), ',');
                $preDate = substr($preDate, strrpos($preDate, ',') + 1);

                if($preDate)
                {
                    $preValue = $sets[$preDate];
                    $preValue = $preValue->value;
                }
            }

            $data[] = isset($sets[$date]) ? $sets[$date]->value : $preValue;
        }

        return $data;
    }

    /**
     * 转换日期格式。
     * Convert date format.
     *
     * @param  array  $dateList
     * @param  string $format
     * @access public
     * @return array
     */
    public function convertFormat(array $dateList, string $format = 'Y-m-d'): array
    {
        foreach($dateList as $i => $date) $dateList[$i] = date($format, strtotime($date));
        return $dateList;
    }

    /**
     * 获取系统的 URL。
     * Get System URL.
     *
     * @access public
     * @return string
     */
    public function getSysURL(): string
    {
        if(isset($this->config->mail->domain)) return $this->config->mail->domain;

        /* Ger URL when run in shell. */
        if(PHP_SAPI == 'cli')
        {
            $url  = parse_url(trim($this->server->argv[1]));
            $port = empty($url['port']) || $url['port'] == 80 ? '' : $url['port'];
            $host = empty($port) ? $url['host'] : $url['host'] . ':' . $port;
            return $url['scheme'] . '://' . $host;
        }
        else
        {
            return common::getSysURL();
        }
    }

    /**
     * 获取用户的 bugs。
     * Get user bugs.
     *
     * @access public
     * @return array
     */
    public function getUserBugs(): array
    {
        return $this->dao->select('t1.id, t1.title, t2.account as user, t1.deadline')
            ->from(TABLE_BUG)->alias('t1')
            ->leftJoin(TABLE_USER)->alias('t2')
            ->on('t1.assignedTo = t2.account')
            ->where('t1.assignedTo')->ne('')
            ->andWhere('t1.assignedTo')->ne('closed')
            ->andWhere('t1.deleted')->eq(0)
            ->andWhere('t2.deleted')->eq(0)
            ->andWhere('t1.deadline', true)->isNull()
            ->orWhere('t1.deadline')->lt(date(DT_DATE1, strtotime('+4 day')))
            ->markRight(1)
            ->fetchGroup('user');
    }

    /**
     * 获取用户的任务。
     * Get user tasks.
     *
     * @access public
     * @return void
     */
    public function getUserTasks(): array
    {
        return $this->dao->select('t1.id, t1.name, t2.account as user, t1.deadline')->from(TABLE_TASK)->alias('t1')
            ->leftJoin(TABLE_USER)->alias('t2')->on('t1.assignedTo = t2.account')
            ->leftJoin(TABLE_EXECUTION)->alias('t3')->on('t1.execution = t3.id')
            ->leftJoin(TABLE_PROJECT)->alias('t4')->on('t1.project = t4.id')
            ->where('t1.assignedTo')->ne('')
            ->andWhere('t1.deleted')->eq(0)
            ->andWhere('t2.deleted')->eq(0)
            ->andWhere('t3.deleted')->eq(0)
            ->andWhere('t4.deleted')->eq(0)
            ->andWhere('t1.status')->in('wait,doing')
            ->andWhere('t3.status')->ne('suspended')
            ->andWhere('t1.deadline', true)->isNull()
            ->orWhere('t1.deadline')->lt(date(DT_DATE1, strtotime('+4 day')))
            ->markRight(1)
            ->fetchGroup('user');
    }

    /**
     * 获取用户的待办。
     * Get user todos.
     *
     * @access public
     * @return array
     */
    public function getUserTodos(): array
    {
        $users = $this->loadModel('user')->getPairs('nodeleted');
        $rows  = $this->dao->select('*')->from(TABLE_TODO)
            ->where('cycle')->eq(0)
            ->andWhere('deleted')->eq(0)
            ->andWhere('status')->in('wait,doing')
            ->fetchAll();

        $todos = array();
        foreach($rows as $todo)
        {
            $user = !empty($todo->assignedTo) ? $todo->assignedTo : $todo->account;
            if(!isset($users[$user])) continue;

            if($todo->type == 'task') $todo->name = $this->dao->findById($todo->objectID)->from(TABLE_TASK)->fetch('name');
            if($todo->type == 'bug')  $todo->name = $this->dao->findById($todo->objectID)->from(TABLE_BUG)->fetch('title');

            $todos[$user][] = $todo;
        }
        return $todos;
    }

    /**
     * 获取用户的测试单。
     * Get user testTasks.
     *
     * @access public
     * @return array
     */
    public function getUserTestTasks(): array
    {
        return $this->dao->select('t1.*, t2.account as user')->from(TABLE_TESTTASK)->alias('t1')
            ->leftJoin(TABLE_USER)->alias('t2')->on('t1.owner = t2.account')
            ->where('t1.deleted')->eq('0')
            ->andWhere('t2.deleted')->eq('0')
            ->andWhere("(t1.status='wait' OR t1.status='doing')")
            ->fetchGroup('user');
    }

    /**
     * 获取用户的卡片。
     * Get user kanban cards.
     *
     * @access public
     * @return array
     */
    public function getUserKanbanCards(): array
    {
        $expireDays = isset($this->config->kanban->reminder->expireDays) ? $this->config->kanban->reminder->expireDays : 1;
        $cards = $this->dao->select('t1.id, t1.name, t1.assignedTo, t1.end as deadline, t1.kanban')
            ->from(TABLE_KANBANCARD)->alias('t1')
            ->leftJoin(TABLE_KANBAN)->alias('t2')->on('t1.kanban = t2.id')
            ->where('t1.assignedTo')->ne('')
            ->andWhere('t2.status')->eq('active')
            ->andWhere('t1.progress')->lt(100)
            ->andWhere('t1.archived')->eq(0)
            ->andWhere('t1.deleted')->eq(0)
            ->andWhere('t1.end')->lt(date(DT_DATE1, strtotime('+' . $expireDays . ' day')))
            ->fetchAll();

        $cardGroups = array();
        foreach($cards as $card)
        {
            $assignedToList = explode(',', $card->assignedTo);
            foreach($assignedToList as $assignedTo)
            {
                $cardGroups[$assignedTo][] = $card;
            }
        }

        return $cardGroups;
    }

    /**
     * 获取用户今年的登录次数。
     * Get user login count in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return int
     */
    public function getUserYearLogins(array $accounts, string $year): int
    {
        return $this->dao->select('COUNT(1) AS count')->from(TABLE_ACTION)->where('actor')->in($accounts)->andWhere('LEFT(date, 4)')->eq($year)->andWhere('action')->eq('login')->fetch('count');
    }

    /**
     * 获取用户本年的操作数。
     * Get user action count in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return int
     */
    public function getUserYearActions(array $accounts, string $year, bool $deptEmpty = true): int
    {
        return $this->dao->select('COUNT(1) AS count')->from(TABLE_ACTION)
            ->where('LEFT(date, 4)')->eq($year)
            ->beginIF($accounts)->andWhere('actor')->in($accounts)->fi()
            ->fetch('count');
    }

    /**
     * 获取用户某年的动态数量。
     * Get contribution count in this year of accounts.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return int
     */
    public function getUserYearContributionCount(array $accounts, string $year): int
    {
        $stmt = $this->dao->select('id,objectType,action')->from(TABLE_ACTION)
            ->where('LEFT(date, 4)')->eq($year)
            ->andWhere('objectType')->in(array_keys($this->config->report->annualData['contributionCount']))
            ->beginIF($accounts)->andWhere('actor')->in($accounts)->fi()
            ->query();

        $count = 0;
        while($action = $stmt->fetch())
        {
            if(isset($this->config->report->annualData['contributionCount'][$action->objectType][strtolower($action->action)])) $count ++;
        }

        return $count;
    }

    /**
     * 获取贡献数的提示信息。
     * Get tips of contribution count.
     *
     * @param  string $mode
     * @access public
     * @return array
     */
    public function getContributionCountTips($mode)
    {
        if($this->config->edition == 'open')
        {
            unset($this->lang->report->contributionCountObject['audit']);
            unset($this->lang->report->contributionCountObject['issue']);
            unset($this->lang->report->contributionCountObject['risk']);
            unset($this->lang->report->contributionCountObject['qa']);
            unset($this->lang->report->contributionCountObject['feedback']);
            unset($this->lang->report->contributionCountObject['ticket']);
        }
        if($this->config->edition == 'biz')
        {
            unset($this->lang->report->contributionCountObject['audit']);
            unset($this->lang->report->contributionCountObject['issue']);
            unset($this->lang->report->contributionCountObject['risk']);
            unset($this->lang->report->contributionCountObject['qa']);
        }

        $tips = isset($this->lang->report->tips->contributionCount[$mode]) ? $this->lang->report->tips->contributionCount[$mode] . '<br>' : $this->lang->report->tips->contributionCount['company'] . '<br>';
        foreach($this->lang->report->contributionCountObject as $objectTip) $tips .= $objectTip . '<br>';
        return $tips;
    }

    /**
     * 获取用户某年的动态数据。
     * Get user contributions data in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return array
     */
    public function getUserYearContributions(array $accounts, string $year): array
    {
        /* Get required actions for annual report. */
        $filterActions = array();
        $stmt          = $this->dao->select('*')->from(TABLE_ACTION)
            ->where('LEFT(date, 4)')->eq($year)
            ->andWhere('objectType')->in(array_keys($this->config->report->annualData['contributions']))
            ->beginIF($accounts)->andWhere('actor')->in($accounts)->fi()
            ->orderBy('objectType,objectID,id')
            ->query();

        $tplData['project'] = $this->dao->select('id')->from(TABLE_PROJECT)->where('isTpl')->eq('1')->fetchPairs();
        $tplData['task']    = $this->dao->select('id')->from(TABLE_TASK)->where('isTpl')->eq('1')->fetchPairs();

        while($action = $stmt->fetch())
        {
            if($action->objectType == 'task' && isset($tplData['task'][$action->objectID])) continue; // 过滤模板任务
            if(in_array($action->objectType, array('project', 'execution')) && isset($tplData['project'][$action->objectID])) continue; // 过滤模板项目和执行
            if(isset($this->config->report->annualData['contributions'][$action->objectType][strtolower($action->action)])) $filterActions[$action->objectType][$action->objectID][$action->id] = $action;
        }

        /* Only get undeleted actions. */
        $actionGroups = array();
        foreach($filterActions as $objectType => $objectActions)
        {
            $deletedIdList = $this->dao->select('id')->from($this->config->objectTables[$objectType])->where('deleted')->eq('1')->andWhere('id')->in(array_keys($objectActions))->fetchPairs();

            foreach($objectActions as $actions)
            {
                foreach($actions as $action)
                {
                    if(!isset($deletedIdList[$action->id])) $actionGroups[$objectType][$action->id] = $action;
                }
            }
        }

        /* Calculate the number of actions . */
        $contributions = array();
        foreach($actionGroups as $objectType => $actions)
        {
            foreach($actions as $action)
            {
                $actionName = $this->config->report->annualData['contributions'][$objectType][strtolower($action->action)];
                $type       = $actionName == 'svnCommit' || $actionName == 'gitCommit' ? 'repo' : $objectType;
                if(!isset($contributions[$type][$actionName])) $contributions[$type][$actionName] = 0;
                $contributions[$type][$actionName] += 1;
            }
        }
        $contributions['case']['run'] = $this->dao->select('COUNT(1) AS count')->from(TABLE_TESTRESULT)->alias('t1')
            ->leftJoin(TABLE_CASE)->alias('t2')->on('t1.case=t2.id')
            ->where('LEFT(t1.date, 4)')->eq($year)
            ->andWhere('t2.deleted')->eq('0')
            ->beginIF($accounts)->andWhere('t1.lastRunner')->in($accounts)->fi()
            ->fetch('count');

        return $contributions;
    }

    /**
     * 获取用户某年的待办统计。
     * Get user todo stat in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return object
     */
    public function getUserYearTodos(array $accounts, string $year): object
    {
        return $this->dao->select("COUNT(1) AS count, sum(if((`status` != 'done'), 1, 0)) AS `undone`, sum(if((`status` = 'done'), 1, 0)) AS `done`")->from(TABLE_TODO)
            ->where('LEFT(date, 4)')->eq($year)
            ->andWhere('deleted')->eq('0')
            ->andWhere('vision')->eq($this->config->vision)
            ->beginIF($accounts)->andWhere('account')->in($accounts)->fi()
            ->fetch();
    }

    /**
     * 获取用户某年的工时统计。
     * Get user effort stat in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return object
     */
    public function getUserYearEfforts(array $accounts, string $year): object
    {
        $effort = $this->dao->select('COUNT(1) AS count, SUM(consumed) AS consumed')->from(TABLE_EFFORT)
            ->where('LEFT(date, 4)')->eq($year)
            ->andWhere('deleted')->eq(0)
            ->beginIF($accounts)->andWhere('account')->in($accounts)->fi()
            ->fetch();

        $effort->consumed = !empty($effort->consumed) ? round($effort->consumed, 2) : 0;
        return $effort;
    }

    /**
     * Get global effort summary.
     *
     * @param  array  $filters
     * @access public
     * @return object
     */
    public function getGlobalEffortSummary(array $filters = array()): object
    {
        $sql = $this->buildGlobalEffortDataSQL($filters);

        $summary = $this->dao->query("SELECT COUNT(1) AS records, ROUND(SUM(consumed), 2) AS consumed, COUNT(DISTINCT IF(project > 0, project, NULL)) AS projectCount, COUNT(DISTINCT IF(objectType = 'task', objectID, NULL)) AS taskCount, COUNT(DISTINCT IF(objectType IN ('requirement', 'story'), objectID, NULL)) AS requirementCount, COUNT(DISTINCT IF(account != '', account, NULL)) AS userCount FROM ($sql) t")->fetch();

        /* Count distinct product IDs from comma-wrapped text (ZenTao stores zt_effort.product as ',id,id,'). */
        $productIDSet = array();
        foreach($this->dao->query("SELECT DISTINCT product FROM ($sql) t WHERE product IS NOT NULL AND product <> ''")->fetchAll() as $row)
        {
            foreach(explode(',', trim((string)$row->product, ',')) as $productID)
            {
                $productID = (int)$productID;
                if($productID > 0) $productIDSet[$productID] = true;
            }
        }

        $summary->records          = (int)$summary->records;
        $summary->consumed         = $summary->consumed === null ? 0 : round((float)$summary->consumed, 2);
        $summary->productCount     = count($productIDSet);
        $summary->projectCount     = (int)$summary->projectCount;
        $summary->taskCount        = (int)$summary->taskCount;
        $summary->requirementCount = (int)$summary->requirementCount;
        $summary->userCount        = (int)$summary->userCount;

        return $summary;
    }

    /**
     * Get global effort distribution by dimension.
     *
     * @param  string $dimension
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getGlobalEffortDistribution(string $dimension = 'product', array $filters = array()): array
    {
        $fieldMap = array(
            'product'     => 'product',
            'project'     => 'project',
            'execution'   => 'execution',
            'task'        => 'objectID',
            'requirement' => 'objectID',
            'account'     => 'account',
            'objectType'  => 'objectType',
            'date'        => 'date'
        );
        if(!isset($fieldMap[$dimension])) $dimension = 'product';

        if($dimension == 'task')        $filters['objectType'] = 'task';
        if($dimension == 'requirement') $filters['objectType'] = 'requirement';

        $sql   = $this->buildGlobalEffortDataSQL($filters);
        $field = $fieldMap[$dimension];
        $rows  = $this->dao->query("SELECT $field AS dimension, COUNT(1) AS records, ROUND(SUM(consumed), 2) AS consumed FROM ($sql) t GROUP BY $field ORDER BY consumed DESC, records DESC, dimension ASC")->fetchAll();

        $total = 0.0;
        foreach($rows as $row) $total += (float)$row->consumed;
        foreach($rows as $row)
        {
            $row->dimension = (string)$row->dimension;
            $row->records   = (int)$row->records;
            $row->consumed  = round((float)$row->consumed, 2);
            $row->percent   = $total > 0 ? round($row->consumed / $total, 4) : 0;
        }

        return $rows;
    }

    /**
     * Get global effort health and early risk indicators.
     *
     * @param  array  $filters
     * @access public
     * @return object
     */
    public function getGlobalEffortHealth(array $filters = array()): object
    {
        $sql     = $this->buildGlobalEffortDataSQL($filters);
        $summary = $this->dao->query("SELECT ROUND(SUM(consumed), 2) AS totalConsumed, COUNT(DISTINCT account) AS activeUsers, COUNT(DISTINCT date) AS activeDays FROM ($sql) t")->fetch();

        $health = new stdclass();
        $health->totalConsumed   = $summary && $summary->totalConsumed !== null ? round((float)$summary->totalConsumed, 2) : 0;
        $health->activeUsers     = $summary ? (int)$summary->activeUsers : 0;
        $health->activeDays      = $summary ? (int)$summary->activeDays : 0;
        $health->avgHoursPerUser = $health->activeUsers > 0 ? round($health->totalConsumed / $health->activeUsers, 2) : 0;
        $health->avgHoursPerDay  = $health->activeDays > 0 ? round($health->totalConsumed / $health->activeDays, 2) : 0;

        $overload = $this->dao->query("SELECT COUNT(1) AS overloadDays FROM (SELECT account, date, SUM(consumed) AS dayConsumed FROM ($sql) t GROUP BY account, date HAVING dayConsumed > 8) d")->fetch();
        $health->overloadDays = $overload ? (int)$overload->overloadDays : 0;

        $topAccount = $this->dao->query("SELECT account, ROUND(SUM(consumed), 2) AS consumed FROM ($sql) t GROUP BY account ORDER BY consumed DESC, account ASC LIMIT 1")->fetch();
        $health->topAccount         = $topAccount ? (string)$topAccount->account : '';
        $health->topAccountConsumed = $topAccount ? round((float)$topAccount->consumed, 2) : 0;
        $health->topAccountShare    = $health->totalConsumed > 0 ? round($health->topAccountConsumed / $health->totalConsumed, 4) : 0;

        $health->riskLevel = 'low';
        if($health->overloadDays > 0 || $health->topAccountShare >= 0.4) $health->riskLevel = 'medium';
        if($health->overloadDays >= 3 || $health->topAccountShare >= 0.6) $health->riskLevel = 'high';

        return $health;
    }

    /**
     * Get product or project cost/progress indicators.
     *
     * @param  string $scope product|project
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getGlobalEffortCostProgress(string $scope = 'project', array $filters = array()): array
    {
        if(!in_array($scope, array('product', 'project'))) $scope = 'project';

        $groups = array();
        foreach($this->getGlobalEffortRecords($filters) as $row)
        {
            $scopeID = (int)$row->{$scope};
            if($scopeID <= 0) continue;

            if(!isset($groups[$scopeID]))
            {
                $groups[$scopeID] = (object)array(
                    'scope'          => $scope,
                    'scopeID'        => $scopeID,
                    'consumed'       => 0.0,
                    'left'           => 0.0,
                    'objects'        => 0,
                    'overrunObjects' => 0,
                    'progress'       => 0.0,
                    'riskLevel'      => 'low',
                    'objectMap'      => array()
                );
            }

            $groups[$scopeID]->consumed += (float)$row->consumed;

            $objectKey = "{$row->source}:{$row->objectType}:{$row->objectID}";
            if(!isset($groups[$scopeID]->objectMap[$objectKey]))
            {
                $groups[$scopeID]->objectMap[$objectKey] = (object)array('left' => (float)$row->left, 'date' => $row->date, 'id' => (int)$row->id);
            }
            else
            {
                $latest = $groups[$scopeID]->objectMap[$objectKey];
                if($row->date > $latest->date || ($row->date == $latest->date && (int)$row->id > $latest->id))
                {
                    $latest->left = (float)$row->left;
                    $latest->date = $row->date;
                    $latest->id   = (int)$row->id;
                }
            }
        }

        foreach($groups as $group)
        {
            $group->objects = count($group->objectMap);
            foreach($group->objectMap as $object)
            {
                $group->left += (float)$object->left;
                if((float)$object->left < 0) $group->overrunObjects++;
            }

            $positiveLeft    = max(0, $group->left);
            $group->consumed = round($group->consumed, 2);
            $group->left     = round($group->left, 2);
            $group->progress = ($group->consumed + $positiveLeft) > 0 ? round($group->consumed / ($group->consumed + $positiveLeft), 2) : 0;

            $group->riskLevel = 'low';
            if($group->progress < 0.3 && $group->left > $group->consumed) $group->riskLevel = 'medium';
            if($group->overrunObjects > 0) $group->riskLevel = 'high';

            unset($group->objectMap);
        }

        usort($groups, function($a, $b)
        {
            if($a->consumed == $b->consumed) return $a->scopeID <=> $b->scopeID;
            return $a->consumed < $b->consumed ? 1 : -1;
        });

        return $groups;
    }

    /**
     * Get management summary metrics for global effort.
     *
     * @param  array  $filters
     * @access public
     * @return object
     */
    public function getGlobalEffortManagementMetrics(array $filters = array()): object
    {
        $records = $this->getGlobalEffortRecords($filters);

        $metrics = (object)array('totalConsumed' => 0.0, 'estimatedConsumed' => 0.0, 'estimatedTotal' => 0.0, 'estimatedPercent' => 0.0);
        $taskIDs = array();
        foreach($records as $record)
        {
            $metrics->totalConsumed += (float)$record->consumed;
            if($record->objectType == 'task') $taskIDs[(int)$record->objectID] = (int)$record->objectID;
        }

        $taskEstimates = array();
        if($taskIDs)
        {
            $tasks = $this->dao->select('id,estimate')->from(TABLE_TASK)->where('id')->in($taskIDs)->andWhere('deleted')->eq('0')->fetchPairs('id', 'estimate');
            foreach($tasks as $taskID => $estimate)
            {
                if((float)$estimate > 0) $taskEstimates[(int)$taskID] = (float)$estimate;
            }
        }

        foreach($records as $record)
        {
            if($record->objectType != 'task') continue;
            if(!isset($taskEstimates[(int)$record->objectID])) continue;
            $metrics->estimatedConsumed += (float)$record->consumed;
        }
        foreach($taskEstimates as $estimate) $metrics->estimatedTotal += $estimate;

        $metrics->totalConsumed     = round($metrics->totalConsumed, 2);
        $metrics->estimatedConsumed = round($metrics->estimatedConsumed, 2);
        $metrics->estimatedTotal    = round($metrics->estimatedTotal, 2);
        $metrics->estimatedPercent  = $metrics->estimatedTotal > 0 ? round($metrics->estimatedConsumed / $metrics->estimatedTotal, 2) : 0;

        return $metrics;
    }

    /**
     * Get object type distribution for global effort.
     *
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getGlobalEffortObjectTypeDistribution(array $filters = array()): array
    {
        $groups = array();
        $total  = 0.0;
        foreach($this->getGlobalEffortRecords($filters) as $record)
        {
            $type = $this->normalizeGlobalEffortObjectType((string)$record->objectType);
            if(!isset($groups[$type])) $groups[$type] = (object)array('type' => $type, 'label' => $this->getGlobalEffortObjectTypeLabel($type), 'consumed' => 0.0, 'percent' => 0.0);
            $groups[$type]->consumed += (float)$record->consumed;
            $total += (float)$record->consumed;
        }

        foreach($groups as $group)
        {
            $group->consumed = round($group->consumed, 2);
            $group->percent  = $total > 0 ? round($group->consumed / $total, 4) : 0;
        }

        usort($groups, function($a, $b)
        {
            if($a->consumed == $b->consumed) return strcmp($a->type, $b->type);
            return $a->consumed < $b->consumed ? 1 : -1;
        });

        return $groups;
    }

    /**
     * Get stale objects in selected effort range.
     *
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getGlobalEffortStaleObjects(array $filters = array()): array
    {
        $threshold = !empty($filters['staleDays']) ? (int)$filters['staleDays'] : 5;
        $end       = !empty($filters['end']) ? $filters['end'] : date('Y-m-d');
        $endTime   = strtotime($end);

        $objects = array();
        foreach($this->getGlobalEffortRecords($filters) as $record)
        {
            $key = "{$record->objectType}:{$record->objectID}";
            if(!isset($objects[$key])) $objects[$key] = (object)array('objectType' => (string)$record->objectType, 'objectID' => (int)$record->objectID, 'dates' => array());
            $objects[$key]->dates[] = $record->date;
        }

        $staleObjects = array();
        foreach($objects as $object)
        {
            $dates = array_values(array_unique($object->dates));
            sort($dates);

            $maxGap = 0;
            for($i = 1; $i < count($dates); $i++)
            {
                $gap = (int)floor((strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400);
                if($gap > $maxGap) $maxGap = $gap;
            }

            if($dates && $endTime)
            {
                $lastGap = (int)floor(($endTime - strtotime(end($dates))) / 86400);
                if($lastGap > $maxGap) $maxGap = $lastGap;
            }

            if($maxGap > $threshold)
            {
                $staleObjects[] = (object)array('objectType' => $object->objectType, 'objectID' => $object->objectID, 'staleDays' => $maxGap);
            }
        }

        usort($staleObjects, function($a, $b)
        {
            if($a->staleDays == $b->staleDays) return strcmp($a->objectType . $a->objectID, $b->objectType . $b->objectID);
            return $a->staleDays < $b->staleDays ? 1 : -1;
        });

        return $staleObjects;
    }

    /**
     * Get account stacked effort distribution.
     *
     * @param  array  $filters
     * @access public
     * @return array
     */
    public function getGlobalEffortAccountStack(array $filters = array()): array
    {
        $accounts = array();
        foreach($this->getGlobalEffortRecords($filters) as $record)
        {
            $account = (string)$record->account;
            $type    = $this->normalizeGlobalEffortObjectType((string)$record->objectType);
            if(!isset($accounts[$account])) $accounts[$account] = (object)array('account' => $account, 'total' => 0.0, 'segments' => array());
            if(!isset($accounts[$account]->segments[$type])) $accounts[$account]->segments[$type] = (object)array('type' => $type, 'label' => $this->getGlobalEffortObjectTypeLabel($type), 'consumed' => 0.0, 'percent' => 0.0);

            $accounts[$account]->total += (float)$record->consumed;
            $accounts[$account]->segments[$type]->consumed += (float)$record->consumed;
        }

        foreach($accounts as $account)
        {
            $account->total = round($account->total, 2);
            foreach($account->segments as $segment)
            {
                $segment->consumed = round($segment->consumed, 2);
                $segment->percent  = $account->total > 0 ? round($segment->consumed / $account->total, 4) : 0;
            }
            $segments = array_values($account->segments);
            usort($segments, function($a, $b)
            {
                if($a->consumed == $b->consumed) return strcmp($a->type, $b->type);
                return $a->consumed < $b->consumed ? 1 : -1;
            });
            $account->segments = $segments;
        }

        $accounts = array_values($accounts);
        usort($accounts, function($a, $b)
        {
            if($a->total == $b->total) return strcmp($a->account, $b->account);
            return $a->total < $b->total ? 1 : -1;
        });

        return $accounts;
    }

    /**
     * Get global effort records.
     *
     * @param  array       $filters
     * @param  object|null $pager
     * @access public
     * @return array
     */
    public function getGlobalEffortRecords(array $filters = array(), ?object $pager = null): array
    {
        $sql      = $this->buildGlobalEffortDataSQL($filters);
        $limitSQL = '';
        if($pager)
        {
            $offset   = max(0, ((int)$pager->pageID - 1) * (int)$pager->recPerPage);
            $limitSQL = ' LIMIT ' . $offset . ', ' . (int)$pager->recPerPage;
        }

        $rows = $this->dao->query("SELECT * FROM ($sql) t ORDER BY date DESC, id DESC$limitSQL")->fetchAll();
        foreach($rows as $row)
        {
            $row->product  = (int)$row->product;
            $row->project  = (int)$row->project;
            $row->execution = (int)$row->execution;
            $row->objectID = (int)$row->objectID;
            $row->consumed = round((float)$row->consumed, 2);
            $row->left     = round((float)$row->left, 2);
        }

        return $rows;
    }

    /**
     * Build global effort CSV.
     *
     * @param  array  $filters
     * @access public
     * @return string
     */
    public function buildGlobalEffortCSV(array $filters = array()): string
    {
        $fields = array('来源', '日期', '产品', '项目', '执行', '对象类型', '对象ID', '人员', '耗时', '剩余', '工作内容');
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $fields);

        foreach($this->getGlobalEffortRecords($filters) as $row)
        {
            fputcsv($handle, array($row->source, $row->date, $row->product, $row->project, $row->execution, $row->objectType, $row->objectID, $row->account, $row->consumed, $row->left, str_replace(array("\r", "\n"), ' ', (string)$row->work)));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Build SQL of global effort records.
     *
     * @param  array  $filters
     * @access private
     * @return string
     */
    private function buildGlobalEffortDataSQL(array $filters = array()): string
    {
        $where = $this->buildGlobalEffortWhere($filters, 'date', 'objectType', 'objectID', 'product', 'project', 'execution', 'account');
        $sqls  = array();
        $sqls[] = "SELECT 'effort' AS source, id, objectType, objectID, CAST(product AS UNSIGNED) AS product, project, execution, account, work, date, consumed, `left` FROM " . TABLE_EFFORT . " WHERE $where";

        if($this->hasObjectEffortTable())
        {
            $objectWhere = $this->buildGlobalEffortWhere($filters, 'date', 'objectType', 'objectID', 'product', 'project', 'execution', 'account');
            $sqls[] = "SELECT 'objecteffort' AS source, id, objectType, objectID, product, project, execution, account, work, date, consumed, `left` FROM " . $this->getObjectEffortTable() . " WHERE $objectWhere";
        }

        return implode(' UNION ALL ', $sqls);
    }

    /**
     * Build global effort where SQL.
     *
     * @param  array  $filters
     * @param  string $dateField
     * @param  string $objectTypeField
     * @param  string $productField
     * @param  string $projectField
     * @param  string $executionField
     * @param  string $accountField
     * @access private
     * @return string
     */
    private function buildGlobalEffortWhere(array $filters, string $dateField, string $objectTypeField, string $objectIDField, string $productField, string $projectField, string $executionField, string $accountField): string
    {
        $conditions = array("deleted = '0'");

        if(!empty($filters['begin']))     $conditions[] = "$dateField >= " . $this->dbh->quote($filters['begin']);
        if(!empty($filters['end']))       $conditions[] = "$dateField <= " . $this->dbh->quote($filters['end']);
        if(!empty($filters['productLine']))
        {
            $productIDs = $this->dao->select('id')->from(TABLE_PRODUCT)->where('line')->eq((int)$filters['productLine'])->andWhere('deleted')->eq('0')->fetchPairs('id', 'id');
            if(!$productIDs)
            {
                $conditions[] = '1 = 0';
            }
            else
            {
                $productClauses = array();
                foreach($productIDs as $productID) $productClauses[] = "FIND_IN_SET(" . (int)$productID . ", $productField) > 0";
                $conditions[] = '(' . implode(' OR ', $productClauses) . ')';
            }
        }
        if(!empty($filters['product']))   $conditions[] = "FIND_IN_SET(" . (int)$filters['product'] . ", $productField) > 0";
        if(!empty($filters['program']))
        {
            $program = $this->dao->select('id,path')->from(TABLE_PROJECT)->where('id')->eq((int)$filters['program'])->andWhere('type')->eq('program')->andWhere('deleted')->eq('0')->fetch();
            $projectIDs = array();
            if($program)
            {
                $projectIDs = $this->dao->select('id')->from(TABLE_PROJECT)
                    ->where('deleted')->eq('0')
                    ->andWhere('type')->in('project,sprint,stage,kanban')
                    ->andWhere("(parent = " . (int)$program->id . " OR path LIKE " . $this->dbh->quote("%,{$program->id},%") . ')')
                    ->fetchPairs('id', 'id');
            }
            $conditions[] = $projectIDs ? "$projectField IN (" . implode(',', array_map('intval', $projectIDs)) . ")" : '1 = 0';
        }
        if(!empty($filters['project']))   $conditions[] = "$projectField = " . (int)$filters['project'];
        if(!empty($filters['execution'])) $conditions[] = "$executionField = " . (int)$filters['execution'];

        if(!empty($filters['account']))
        {
            $accounts = is_array($filters['account']) ? $filters['account'] : array($filters['account']);
            $accounts = array_map(array($this->dbh, 'quote'), $accounts);
            $conditions[] = "$accountField IN (" . implode(',', $accounts) . ")";
        }

        if(!empty($filters['objectType']) && !empty($filters['objectID']) && !empty($filters['includeRelated']) && $filters['objectType'] == 'story')
        {
            $taskIDs = $this->dao->select('id')->from(TABLE_TASK)->where('story')->eq((int)$filters['objectID'])->andWhere('deleted')->eq('0')->fetchPairs('id', 'id');
            $quotedType = $this->dbh->quote($filters['objectType']);
            $relatedConditions = array("($objectTypeField = $quotedType AND $objectIDField = " . (int)$filters['objectID'] . ")");
            if($taskIDs) $relatedConditions[] = "($objectTypeField = 'task' AND $objectIDField IN (" . implode(',', array_map('intval', $taskIDs)) . "))";
            $conditions[] = '(' . implode(' OR ', $relatedConditions) . ')';
        }
        elseif(!empty($filters['objectType']))
        {
            $conditions[] = "$objectTypeField = " . $this->dbh->quote($filters['objectType']);
            if(!empty($filters['objectID'])) $conditions[] = "$objectIDField = " . (int)$filters['objectID'];
        }
        elseif(!empty($filters['objectID']))
        {
            $conditions[] = "$objectIDField = " . (int)$filters['objectID'];
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Normalize object type for management charts.
     *
     * @param  string $objectType
     * @access private
     * @return string
     */
    private function normalizeGlobalEffortObjectType(string $objectType): string
    {
        return in_array($objectType, array('epic', 'requirement', 'story', 'bug', 'task')) ? $objectType : 'other';
    }

    /**
     * Get label of normalized object type.
     *
     * @param  string $objectType
     * @access private
     * @return string
     */
    private function getGlobalEffortObjectTypeLabel(string $objectType): string
    {
        $labels = array('epic' => '业务需求', 'requirement' => '用户需求', 'story' => '研发需求', 'bug' => 'Bug', 'task' => '任务', 'other' => '其他');
        return zget($labels, $objectType, $objectType);
    }

    /**
     * Get object effort table.
     *
     * @access private
     * @return string
     */
    private function getObjectEffortTable(): string
    {
        return defined('TABLE_OBJECTEFFORT') ? TABLE_OBJECTEFFORT : '`' . $this->config->db->prefix . 'objecteffort`';
    }

    /**
     * Check object effort table exists.
     *
     * @access private
     * @return bool
     */
    private function hasObjectEffortTable(): bool
    {
        $table = trim($this->getObjectEffortTable(), '`');
        $row   = $this->dbh->query('SHOW TABLES LIKE ' . $this->dbh->quote($table))->fetch();
        return !empty($row);
    }

    /**
     * 获取用户某年的产品下创建的需求、计划，创建和关闭的需求数量统计。
     * Get count of created story,plan and closed story by accounts every product in this year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return array
     */
    public function getUserYearProducts(array $accounts, string $year): array
    {
        /* Get changed products in this year. */
        list($products, $planGroups, $createdStoryStats, $closedStoryStats) = $this->reportTao->getAnnualProductStat($accounts, $year);

        /* Merge created plan, created story and closed story in every product. */
        foreach($products as $productID => $product)
        {
            $product->plan        = 0;
            $product->requirement = 0;
            $product->story       = 0;
            $product->epic        = 0;
            $product->closed      = 0;

            $plans = zget($planGroups, $productID, array());
            if($plans) $product->plan = count($plans);

            $createdStoryStat = zget($createdStoryStats, $productID, '');
            if($createdStoryStat)
            {
                $product->requirement = $createdStoryStat->requirement;
                $product->story       = $createdStoryStat->story;
                $product->epic        = $createdStoryStat->epic;
            }

            $closedStoryStat = zget($closedStoryStats, $productID, '');
            if($closedStoryStat) $product->closed = $closedStoryStat->closed;
        }

        return $products;
    }

    /**
     * 获取用户某年内每次执行的已完成任务、故事和已解决的bug。
     * Get count of finished task, story and resolved bug by accounts every executions in a year.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return array
     */
    public function getUserYearExecutions(array $accounts, string $year): array
    {
        /* Get changed executions in this year. */
        list($executions, $finishedTask, $finishedStory, $resolvedBugs) = $this->reportTao->getAnnualExecutionStat($accounts, $year);

        foreach($executions as $executionID => $execution)
        {
            $execution->task  = zget($finishedTask,  $executionID, 0);
            $execution->story = zget($finishedStory, $executionID, 0);
            $execution->bug   = zget($resolvedBugs,  $executionID, 0);
        }

        return $executions;
    }

    /**
     * 获取所有时间的状态，包括需求、任务和 bug。
     * Get status stat that is all time, include story, task and bug.
     *
     * @access public
     * @return array
     */
    public function getAllTimeStatusStat(): array
    {
        $statusStat = array();
        $statusStat['story'] = $this->dao->select('status, count(status) as count')->from(TABLE_STORY)->where('deleted')->eq(0)->andWhere('type')->eq('story')->groupBy('status')->fetchPairs('status', 'count');
        $statusStat['task']  = $this->dao->select('status, count(status) as count')->from(TABLE_TASK)->where('deleted')->eq(0)->groupBy('status')->fetchPairs('status', 'count');
        $statusStat['bug']   = $this->dao->select('status, count(status) as count')->from(TABLE_BUG)->where('deleted')->eq(0)->groupBy('status')->fetchPairs('status', 'count');
        return $statusStat;
    }

    /**
     * 获取年度需求、任务或者 bug 的状态统计。
     * Get year object stat, include status and action stat
     *
     * @param  array  $accounts
     * @param  string $year
     * @param  string $objectType story|task|bug
     * @access public
     * @return array
     */
    public function getYearObjectStat(array $accounts, string $year, string $objectType): array
    {
        if($objectType == 'story') $table = TABLE_STORY;
        if($objectType == 'task')  $table = TABLE_TASK;
        if($objectType == 'bug')   $table = TABLE_BUG;
        if(empty($table)) return array();

        $objectTypeList = $objectType == 'story' ? array('story', 'requirement', 'epic') : array($objectType);
        $months = $this->getYearMonths($year);
        $stmt   = $this->dao->select('t1.*, t2.status, t2.deleted')->from(TABLE_ACTION)->alias('t1')
            ->leftJoin($table)->alias('t2')->on('t1.objectID=t2.id')
            ->where('LEFT(t1.date, 4)')->eq($year)
            ->andWhere('t1.objectType')->in($objectTypeList)
            ->andWhere('t1.action')->in($this->config->report->annualData['monthAction'][$objectType])
            ->beginIF($accounts)->andWhere('t1.actor')->in($accounts)->fi()
            ->query();

        /* Build object action stat and object status stat. */
        $actionStat = array();
        $statusStat = array();
        while($action = $stmt->fetch())
        {
            /* Story, bug can from feedback and ticket, task can from feedback, change this action down to opened. */
            $lowerAction = strtolower($action->action);
            if(in_array($lowerAction, array('fromfeedback', 'fromticket'))) $lowerAction = 'opened';

            $objectID = $action->objectID;
            if($action->deleted == '0' && $lowerAction == 'opened')
            {
                if(!isset($statusStat[$action->status]))   $statusStat[$action->status] = 0;
                if(!isset($statedObjectIDList[$objectID])) $statusStat[$action->status] ++;
                $statedObjectIDList[$objectID] = $objectID;
            }

            if(!isset($actionStat[$lowerAction]))
            {
                foreach($months as $month) $actionStat[$lowerAction][$month] = 0;
            }

            $month = substr($action->date, 0, 7);
            $actionStat[$lowerAction][$month] += 1;
        }

        return array('statusStat' => $statusStat, 'actionStat' => $actionStat);
    }

    /**
     * 获取年度用例的结果状态和动态统计。
     * Get year case stat, include result and action stat.
     *
     * @param  array  $accounts
     * @param  string $year
     * @access public
     * @return array
     */
    public function getYearCaseStat(array $accounts, string $year): array
    {
        $actionStat = $resultStat = array();
        $months     = $this->getYearMonths($year);
        foreach($months as $month) $actionStat['opened'][$month] = $actionStat['run'][$month] = $actionStat['createBug'][$month] = 0;

        return $this->reportTao->buildAnnualCaseStat($accounts, $year, $actionStat, $resultStat);
    }

    /**
     * 获取年度月份。
     * Get year months.
     *
     * @param  string $year
     * @access public
     * @return array
     */
    public function getYearMonths(string $year): array
    {
        $months = array();
        for($i = 1; $i <= 12; $i ++) $months[] = $year . '-' . sprintf('%02d', $i);

        return $months;
    }

    /**
     * 获取状态总览。
     * Get status overview.
     *
     * @param  string $objectType
     * @param  array  $statusStat
     * @access public
     * @return string
     */
    public function getStatusOverview(string $objectType, array $statusStat): string
    {
        $allCount    = 0;
        $undoneCount = 0;
        foreach($statusStat as $status => $count)
        {
            $allCount += $count;
            if($objectType == 'story' && $status != 'closed') $undoneCount += $count;
            if($objectType == 'task' && $status != 'done' && $status != 'closed' && $status != 'cancel') $undoneCount += $count;
            if($objectType == 'bug' && $status == 'active') $undoneCount += $count;
        }

        $overview = '';
        if($objectType == 'story') $overview .= $this->lang->report->annualData->allStory;
        if($objectType == 'task')  $overview .= $this->lang->report->annualData->allTask;
        if($objectType == 'bug')   $overview .= $this->lang->report->annualData->allBug;
        $overview .= ' &nbsp; ' . $allCount;
        $overview .= '<br />';
        $overview .= $objectType == 'bug' ? $this->lang->report->annualData->unresolve : $this->lang->report->annualData->undone;
        $overview .= ' &nbsp; ' . $undoneCount;

        return $overview;
    }

    /**
     * 测试获取项目状态总览。
     * Get project status overview.
     *
     * @param  array  $accounts
     * @access public
     * @return array
     */
    public function getProjectStatusOverview(array $accounts = array()): array
    {
        $projectStatus = $this->dao->select('t1.id,t1.status')->from(TABLE_PROJECT)->alias('t1')
            ->leftJoin(TABLE_TEAM)->alias('t2')->on("t1.id=t2.root")
            ->where('t1.type')->in('project')
            ->andWhere('t2.type')->eq('project')
            ->beginIF(!empty($accounts))->andWhere('t2.account')->in($accounts)->fi()
            ->andWhere('t1.deleted')->eq(0)
            ->fetchPairs();

        $statusOverview = array();
        foreach($projectStatus as $status)
        {
            if(!isset($statusOverview[$status])) $statusOverview[$status] = 0;
            $statusOverview[$status] ++;
        }

        return $statusOverview;
    }

    /**
     * 为 API 获取输出的数据。
     * Get output data for API.
     *
     * @param  array    $accounts
     * @param  string   $year
     * @access public
     * @return array
     */
    public function getOutput4API(array $accounts, string $year): array
    {
        $processedOutput = array();
        $outputData      = $this->reportTao->getOutputData($accounts, $year);
        foreach($this->config->report->outputData as $objectType => $actions)
        {
            if(!isset($outputData[$objectType])) continue;

            $objectActions = $outputData[$objectType];
            $processedOutput[$objectType]['total'] = array_sum($objectActions);

            foreach($actions as $action => $langCode)
            {
                if(empty($objectActions[$action])) continue;

                $processedOutput[$objectType]['actions'][$langCode]['code']  = $langCode;
                $processedOutput[$objectType]['actions'][$langCode]['name']  = $this->lang->report->annualData->actionList[$langCode];
                $processedOutput[$objectType]['actions'][$langCode]['total'] = $objectActions[$action];
            }
        }

        return $processedOutput;
    }

    /**
     * 获取项目和执行名称。
     * Get project and execution name.
     *
     * @access public
     * @return array
     */
    public function getProjectExecutions(): array
    {
        $executions = $this->dao->select('t1.id, t1.name, t2.name as projectname, t1.status, t1.multiple')
            ->from(TABLE_EXECUTION)->alias('t1')
            ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project=t2.id')
            ->where('t1.deleted')->eq(0)
            ->andWhere('t1.type')->in('stage,sprint')
            ->fetchAll();

        $pairs = array();
        foreach($executions as $execution)
        {
            if($execution->multiple)  $pairs[$execution->id] = $execution->projectname . '/' . $execution->name;
            if(!$execution->multiple) $pairs[$execution->id] = $execution->projectname;
        }

        return $pairs;
    }
}

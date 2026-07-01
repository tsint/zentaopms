<?php
declare(strict_types=1);

class objecteffortModel extends model
{
    public $objectTypes = array('bug', 'story', 'requirement');

    public function isValidObjectType(string $objectType): bool
    {
        return in_array(strtolower($objectType), $this->objectTypes);
    }

    public function normalizeObjectType(string $objectType): string
    {
        return strtolower($objectType);
    }

    public function getStoredObjectType(string $objectType, int $objectID): string
    {
        $objectType = $this->normalizeObjectType($objectType);
        if($objectType == 'story')
        {
            $storyType = $this->dao->select('type')->from(TABLE_STORY)->where('id')->eq($objectID)->fetch('type');
            if($storyType == 'requirement') return 'requirement';
        }

        return $objectType;
    }

    public function getObject(string $objectType, int $objectID): false|object
    {
        $objectType = $this->normalizeObjectType($objectType);
        if(!$this->isValidObjectType($objectType) || $objectID <= 0) return false;

        if($objectType == 'bug')
        {
            return $this->dao->select('id, product, project, execution, status, deleted')->from(TABLE_BUG)->where('id')->eq($objectID)->fetch();
        }

        $story = $this->dao->select('id, product, branch, status, deleted, type, estimate')->from(TABLE_STORY)->where('id')->eq($objectID)->fetch();
        if(!$story) return false;
        if($objectType == 'requirement' && $story->type != 'requirement') return false;
        if($objectType == 'story' && $story->type == 'requirement') $objectType = 'requirement';

        $story->project   = 0;
        $story->execution = 0;

        return $story;
    }

    public function getExecutions(string $objectType, int $objectID): array
    {
        $objectType = $this->normalizeObjectType($objectType);
        if($objectType == 'bug')
        {
            $bug = $this->getObject($objectType, $objectID);
            if(!$bug || !$bug->execution) return array();

            return $this->dao->select('id, project, name')->from(TABLE_EXECUTION)
                ->where('id')->eq($bug->execution)
                ->andWhere('deleted')->eq('0')
                ->fetchAll('id');
        }

        if(!in_array($objectType, array('story', 'requirement'))) return array();

        return $this->dao->select('t2.id, t2.project, t2.name')->from(TABLE_PROJECTSTORY)->alias('t1')
            ->leftJoin(TABLE_EXECUTION)->alias('t2')->on('t1.project=t2.id')
            ->where('t1.story')->eq($objectID)
            ->andWhere('t2.type')->in('sprint,stage,kanban')
            ->andWhere('t2.deleted')->eq('0')
            ->orderBy('t2.id_desc')
            ->fetchAll('id');
    }

    public function getExecutionPairs(string $objectType, int $objectID): array
    {
        $pairs = array();
        foreach($this->getExecutions($objectType, $objectID) as $execution) $pairs[$execution->id] = $execution->name;
        return $pairs;
    }

    public function getProjects(string $objectType, int $objectID): array
    {
        $objectType = $this->normalizeObjectType($objectType);
        if($objectType == 'bug')
        {
            $bug = $this->getObject($objectType, $objectID);
            if(!$bug || !$bug->project) return array();
            return $this->dao->select('id, name')->from(TABLE_PROJECT)->where('id')->eq($bug->project)->andWhere('deleted')->eq('0')->fetchAll('id');
        }
        if(!in_array($objectType, array('story', 'requirement'))) return array();

        return $this->dao->select('t2.id, t2.name')->from(TABLE_PROJECTSTORY)->alias('t1')
            ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project=t2.id')
            ->where('t1.story')->eq($objectID)
            ->andWhere('t2.type')->eq('project')
            ->andWhere('t2.deleted')->eq('0')
            ->fetchAll('id');
    }

    public function getProjectPairs(string $objectType, int $objectID): array
    {
        $pairs = array();
        foreach($this->getProjects($objectType, $objectID) as $project) $pairs[$project->id] = $project->name;
        return $pairs;
    }

    public function canRecord(string $objectType, int $objectID): bool
    {
        if(!$this->app->user->admin && !common::hasPriv('objecteffort', 'record')) return false;

        $object = $this->getObject($objectType, $objectID);
        if(!$object || $object->deleted) return false;
        if(isset($object->status) && $object->status == 'closed') return false;

        return true;
    }

    public function canOperate(object $effort, string $method = ''): bool
    {
        if($this->app->user->admin) return true;
        if($method && !common::hasPriv('objecteffort', $method)) return false;
        return $effort->account == $this->app->user->account || $effort->createdBy == $this->app->user->account;
    }

    public function buildEffort(string $objectType, int $objectID, object $data, bool $allowZeroConsumed = false): false|object
    {
        $objectType = $this->normalizeObjectType($objectType);
        if(!$this->isValidObjectType($objectType))
        {
            dao::$errors['objectType'] = $this->lang->objecteffort->error->objectType;
            return false;
        }

        $object = $this->getObject($objectType, $objectID);
        if(!$object || $object->deleted)
        {
            dao::$errors['objectID'] = $this->lang->objecteffort->error->object;
            return false;
        }
        if(isset($object->status) && $object->status == 'closed')
        {
            dao::$errors['objectID'] = $this->lang->objecteffort->error->closed;
            return false;
        }

        if($objectType == 'bug')
        {
            $executionID = (int)$object->execution;
            $projectID   = (int)$object->project;
            if(isset($data->execution) && (int)$data->execution !== $executionID)
            {
                dao::$errors['execution'] = $this->lang->objecteffort->error->execution;
                return false;
            }
        }
        else
        {
            $executions  = $this->getExecutions($objectType, $objectID);
            $projects    = $this->getProjects($objectType, $objectID);
            $executionID = isset($data->execution) ? (int)$data->execution : 0;
            if(!$executionID && count($executions) > 1)
            {
                dao::$errors['execution'] = $this->lang->objecteffort->error->executionRequired;
                return false;
            }
            if(!$executionID && count($executions) == 1) $executionID = (int)key($executions);
            if($executionID && !isset($executions[$executionID]))
            {
                dao::$errors['execution'] = $this->lang->objecteffort->error->execution;
                return false;
            }

            if($executionID)
            {
                $projectID = (int)$executions[$executionID]->project;
            }
            else
            {
                $projectID = isset($data->project) ? (int)$data->project : 0;
                if(!$projectID && count($projects) > 1)
                {
                    dao::$errors['project'] = $this->lang->objecteffort->error->projectRequired;
                    return false;
                }
                if(!$projectID && count($projects) == 1) $projectID = (int)key($projects);
                if($projectID && !isset($projects[$projectID]))
                {
                    dao::$errors['project'] = $this->lang->objecteffort->error->project;
                    return false;
                }
            }
        }

        $date = isset($data->date) ? $data->date : '';
        if(helper::isZeroDate($date) || $date > helper::today())
        {
            dao::$errors['date'] = $this->lang->objecteffort->error->date;
            return false;
        }

        $consumed = isset($data->consumed) ? $data->consumed : '';
        $left     = isset($data->left) ? $data->left : '';
        $estimate = isset($data->estimate) ? $data->estimate : 0;
        if(!is_numeric($consumed) || $consumed < 0 || (!$allowZeroConsumed && $consumed == 0))
        {
            dao::$errors['consumed'] = $this->lang->objecteffort->error->consumed;
            return false;
        }
        if($left !== '' && !is_numeric($left))
        {
            dao::$errors['left'] = $this->lang->objecteffort->error->left;
            return false;
        }
        if(!is_numeric($estimate) || $estimate < 0) $estimate = 0;
        if($left !== '' && (float)$left < 0)
        {
            $effectiveEstimate = (float)$estimate;
            if($effectiveEstimate == 0) $effectiveEstimate = (float)$this->getSummary($objectType, $objectID)->estimate;
            if($effectiveEstimate == 0)
            {
                dao::$errors['left'] = $this->lang->objecteffort->error->leftZeroEstimate;
                return false;
            }
        }

        if($objectType == 'story' && isset($object->type) && $object->type == 'requirement') $objectType = 'requirement';

        $effort = new stdclass();
        $effort->objectType = $objectType;
        $effort->objectID   = $objectID;
        $effort->product    = isset($object->product) ? (int)$object->product : 0;
        $effort->execution  = $executionID;
        $effort->project    = $projectID;
        $effort->account    = isset($data->account) && $data->account ? $data->account : $this->app->user->account;
        $effort->date       = $date;
        $effort->estimate   = round((float)$estimate, 2);
        $effort->consumed   = round((float)$consumed, 2);
        $effort->left       = $left === '' ? null : round((float)$left, 2);
        $effort->work       = isset($data->work) ? strip_tags((string)$data->work) : '';

        return $effort;
    }

    public function computeAutoLeft(string $objectType, int $objectID, object $effort, float $consumedOffset = 0): float
    {
        if($effort->left !== null) return (float)$effort->left;

        $summary  = $this->getSummary($objectType, $objectID);
        $estimate = $effort->estimate > 0 ? (float)$effort->estimate : (float)$summary->estimate;
        $consumed = (float)$summary->consumed + $consumedOffset + (float)$effort->consumed;

        $left = $estimate - $consumed;
        return round($estimate == 0 ? max(0, $left) : $left, 2);
    }

    public function record(string $objectType, int $objectID, object $data): int|false
    {
        return $this->recordEffort($objectType, $objectID, $data, false);
    }

    public function initializeEstimate(string $objectType, int $objectID, float $estimate): int|false
    {
        if($estimate <= 0) return false;

        $data = new stdclass();
        $data->date     = helper::today();
        $data->estimate = $estimate;
        $data->consumed = 0;
        $data->left     = $estimate;
        $data->work     = '';

        return $this->recordEffort($objectType, $objectID, $data, true);
    }

    protected function recordEffort(string $objectType, int $objectID, object $data, bool $allowZeroConsumed): int|false
    {
        if(!$this->app->user->admin && !common::hasPriv('objecteffort', 'record'))
        {
            dao::$errors['priv'] = $this->lang->objecteffort->error->denied;
            return false;
        }

        $effort = $this->buildEffort($objectType, $objectID, $data, $allowZeroConsumed);
        if(!$effort || dao::isError()) return false;
        if((float)$effort->estimate == 0) $effort->estimate = (float)$this->getSummary($objectType, $objectID)->estimate;
        $effort->left = $this->computeAutoLeft($objectType, $objectID, $effort);

        $effort->createdBy   = $this->app->user->account;
        $effort->createdDate = helper::now();
        $this->dao->insert(TABLE_OBJECTEFFORT)->data($effort)->autoCheck()->exec();
        if(dao::isError()) return false;

        $effortID = (int)$this->dao->lastInsertID();
        $this->loadModel('action')->create('objecteffort', $effortID, 'recordedobjecteffort', $effort->work, "{$effort->objectType}:{$effort->objectID}");
        $this->refreshStatistics(null, $effort);

        return $effortID;
    }

    public function recordBatch(string $objectType, int $objectID, array $workhour): array|false
    {
        if(!$this->app->user->admin && !common::hasPriv('objecteffort', 'record'))
        {
            dao::$errors['priv'] = $this->lang->objecteffort->error->denied;
            return false;
        }

        $records = array();
        foreach($workhour as $id => $record)
        {
            if(empty($record->work) && empty($record->estimate) && empty($record->consumed) && empty($record->left))
            {
                unset($workhour[$id]);
                continue;
            }

            $effort = $this->buildEffort($objectType, $objectID, $record);
            if(!$effort || dao::isError()) return false;
            $records[$id] = $effort;
        }

        $summary       = $this->getSummary($objectType, $objectID);
        $baseConsumed  = (float)$summary->consumed;
        $baseEstimate  = (float)$summary->estimate;
        $effortIdList  = array();
        $consumedTotal = 0.0;
        foreach($records as $effort)
        {
            if((float)$effort->estimate == 0) $effort->estimate = $baseEstimate;
            if($effort->left === null)
            {
                $estimate = $effort->estimate > 0 ? (float)$effort->estimate : $baseEstimate;
                $left = $estimate - ($baseConsumed + $consumedTotal + (float)$effort->consumed);
                $effort->left = round($estimate == 0 ? max(0, $left) : $left, 2);
            }
            $effort->createdBy   = $this->app->user->account;
            $effort->createdDate = helper::now();

            $this->dao->insert(TABLE_OBJECTEFFORT)->data($effort)->autoCheck()->exec();
            if(dao::isError()) return false;

            $effortID = (int)$this->dao->lastInsertID();
            $effortIdList[] = $effortID;
            $this->loadModel('action')->create('objecteffort', $effortID, 'recordedobjecteffort', $effort->work, "{$effort->objectType}:{$effort->objectID}");
            $this->refreshStatistics(null, $effort);
            $consumedTotal += (float)$effort->consumed;
        }

        return $effortIdList;
    }

    public function update(int $effortID, object $data): array|false
    {
        $oldEffort = $this->getByID($effortID);
        if(!$oldEffort || $oldEffort->deleted) return false;
        if(!$this->canOperate($oldEffort, 'edit'))
        {
            dao::$errors['priv'] = $this->lang->objecteffort->error->denied;
            return false;
        }

        $effort = $this->buildEffort($oldEffort->objectType, (int)$oldEffort->objectID, $data);
        if(!$effort || dao::isError()) return false;
        if($effort->left === null)
        {
            $summary = $this->getSummary($oldEffort->objectType, (int)$oldEffort->objectID);
            $offset  = 0 - (float)$oldEffort->consumed;
            $estimate = $effort->estimate > 0 ? (float)$effort->estimate : (float)$summary->estimate;
            $left = $estimate - ((float)$summary->consumed + $offset + (float)$effort->consumed);
            $effort->left = round($estimate == 0 ? max(0, $left) : $left, 2);
        }
        $effort->editedBy   = $this->app->user->account;
        $effort->editedDate = helper::now();

        $this->dao->update(TABLE_OBJECTEFFORT)->data($effort)->autoCheck()->where('id')->eq($effortID)->exec();
        if(dao::isError()) return false;

        $newEffort = $this->getByID($effortID);
        $changes   = common::createChanges($oldEffort, $newEffort);
        $actionID  = $this->loadModel('action')->create('objecteffort', $effortID, 'editedobjecteffort', $effort->work, "{$effort->objectType}:{$effort->objectID}");
        if($changes && $actionID) $this->action->logHistory($actionID, $changes);
        $this->refreshStatistics($oldEffort, $newEffort);

        return $changes;
    }

    public function deleteEffort(int $effortID): bool
    {
        $effort = $this->getByID($effortID);
        if(!$effort || $effort->deleted) return false;
        if(!$this->canOperate($effort, 'delete'))
        {
            dao::$errors['priv'] = $this->lang->objecteffort->error->denied;
            return false;
        }

        $this->dao->update(TABLE_OBJECTEFFORT)
            ->set('deleted')->eq('1')
            ->set('deletedBy')->eq($this->app->user->account)
            ->set('deletedDate')->eq(helper::now())
            ->where('id')->eq($effortID)
            ->exec();
        if(dao::isError()) return false;

        $this->loadModel('action')->create('objecteffort', $effortID, 'deletedobjecteffort', '', "{$effort->objectType}:{$effort->objectID}");
        $this->refreshStatistics($effort, null);

        return true;
    }

    public function getByID(int $effortID): false|object
    {
        return $this->dao->select('*')->from(TABLE_OBJECTEFFORT)->where('id')->eq($effortID)->fetch();
    }

    public function refreshStatistics(object|null $oldEffort, object|null $newEffort): void
    {
        $projectIdList   = array();
        $executionIdList = array();
        foreach(array($oldEffort, $newEffort) as $effort)
        {
            if(!$effort) continue;
            if($effort->project)   $projectIdList[(int)$effort->project] = (int)$effort->project;
            if($effort->execution) $executionIdList[(int)$effort->execution] = (int)$effort->execution;
        }

        if($projectIdList)
        {
            $program = $this->loadModel('program');
            foreach($projectIdList as $projectID) $program->refreshProjectStats($projectID);
        }
        if($executionIdList) $this->loadModel('execution')->computeBurn(array_values($executionIdList));
    }

    public function refreshObjectStatistics(string $objectType, int $objectID): void
    {
        $objectType = $this->getStoredObjectType($objectType, $objectID);
        $scopes = $this->dao->select('DISTINCT project, execution')->from(TABLE_OBJECTEFFORT)
            ->where('objectType')->eq($objectType)
            ->andWhere('objectID')->eq($objectID)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        foreach($scopes as $scope) $this->refreshStatistics($scope, null);
    }

    public function getList(string $objectType, int $objectID, int $limit = 0, ?object $pager = null): array
    {
        $objectType = $this->getStoredObjectType($objectType, $objectID);
        return $this->dao->select('*, work AS content')->from(TABLE_OBJECTEFFORT)
            ->where('objectType')->eq($objectType)
            ->andWhere('objectID')->eq($objectID)
            ->andWhere('deleted')->eq('0')
            ->orderBy('date_desc,id_desc')
            ->beginIF($limit)->limit($limit)->fi()
            ->beginIF($pager)->page($pager)->fi()
            ->fetchAll('id');
    }

    public function getSummary(string $objectType, int $objectID): object
    {
        $objectType = $this->getStoredObjectType($objectType, $objectID);
        $result     = (object)array('estimate' => 0, 'consumed' => 0, 'left' => 0);

        $row = $this->dao->select('MAX(estimate) AS estimate, SUM(consumed) AS consumed')
            ->from(TABLE_OBJECTEFFORT)
            ->where('objectType')->eq($objectType)
            ->andWhere('objectID')->eq($objectID)
            ->andWhere('deleted')->eq('0')
            ->fetch();
        if($row)
        {
            $result->estimate = (float)$row->estimate;
            $result->consumed = (float)$row->consumed;
        }

        if($result->estimate == 0 && in_array($objectType, array('story', 'requirement')))
        {
            $result->estimate = (float)$this->dao->select('estimate')->from(TABLE_STORY)->where('id')->eq($objectID)->andWhere('deleted')->eq('0')->fetch('estimate');
        }

        $lastRow = $this->dao->select('`left`')
            ->from(TABLE_OBJECTEFFORT)
            ->where('objectType')->eq($objectType)
            ->andWhere('objectID')->eq($objectID)
            ->andWhere('deleted')->eq('0')
            ->orderBy('date_desc,id_desc')
            ->limit(1)
            ->fetch();
        if($lastRow) $result->left = (float)$lastRow->left;

        return $result;
    }

    public function getSummaryByExecution(array $executionIdList): array
    {
        if(empty($executionIdList)) return array();

        $rows = $this->dao->select('execution, objectType, objectID, MAX(estimate) AS estimate, SUM(consumed) AS consumed')
            ->from(TABLE_OBJECTEFFORT)
            ->where('execution')->in($executionIdList)
            ->andWhere('deleted')->eq('0')
            ->groupBy('execution, objectType, objectID')
            ->fetchAll();

        $leftRows = $this->dao->select('t1.execution, t1.objectType, t1.objectID, t1.`left`')->from(TABLE_OBJECTEFFORT)->alias('t1')
            ->leftJoin(TABLE_OBJECTEFFORT)->alias('t2')->on('t1.execution=t2.execution AND t1.objectType=t2.objectType AND t1.objectID=t2.objectID AND t2.deleted="0" AND (t2.date > t1.date OR (t2.date = t1.date AND t2.id > t1.id))')
            ->where('t1.execution')->in($executionIdList)
            ->andWhere('t1.deleted')->eq('0')
            ->andWhere('t2.id')->isNull()
            ->fetchAll();

        $leftMap = array();
        foreach($leftRows as $row) $leftMap["{$row->execution}:{$row->objectType}:{$row->objectID}"] = (float)$row->left;
        $inactiveObjects = $this->getInactiveObjectMap($rows);

        $summary = array();
        foreach($rows as $row)
        {
            $execution = (int)$row->execution;
            if(!isset($summary[$execution])) $summary[$execution] = (object)array('estimate' => 0, 'consumed' => 0, 'left' => 0);
            $inactive = isset($inactiveObjects["{$row->objectType}:{$row->objectID}"]);
            $summary[$execution]->estimate += $inactive ? 0 : (float)$row->estimate;
            $summary[$execution]->consumed += (float)$row->consumed;
            $summary[$execution]->left     += $inactive ? 0 : zget($leftMap, "{$row->execution}:{$row->objectType}:{$row->objectID}", 0);
        }

        return $summary;
    }

    public function getSummaryByProject(array $projectIdList): array
    {
        if(empty($projectIdList)) return array();

        $rows = $this->dao->select('project, execution, objectType, objectID, MAX(estimate) AS estimate, SUM(consumed) AS consumed')
            ->from(TABLE_OBJECTEFFORT)
            ->where('project')->in($projectIdList)
            ->andWhere('deleted')->eq('0')
            ->groupBy('project, execution, objectType, objectID')
            ->fetchAll();

        $leftRows = $this->dao->select('t1.project, t1.execution, t1.objectType, t1.objectID, t1.`left`')->from(TABLE_OBJECTEFFORT)->alias('t1')
            ->leftJoin(TABLE_OBJECTEFFORT)->alias('t2')->on('t1.project=t2.project AND t1.execution=t2.execution AND t1.objectType=t2.objectType AND t1.objectID=t2.objectID AND t2.deleted="0" AND (t2.date > t1.date OR (t2.date = t1.date AND t2.id > t1.id))')
            ->where('t1.project')->in($projectIdList)
            ->andWhere('t1.deleted')->eq('0')
            ->andWhere('t2.id')->isNull()
            ->fetchAll();

        $leftMap = array();
        foreach($leftRows as $row) $leftMap["{$row->project}:{$row->execution}:{$row->objectType}:{$row->objectID}"] = (float)$row->left;
        $inactiveObjects = $this->getInactiveObjectMap($rows);

        $summary = array();
        foreach($rows as $row)
        {
            $projectID = (int)$row->project;
            if(!isset($summary[$projectID])) $summary[$projectID] = (object)array('estimate' => 0, 'consumed' => 0, 'left' => 0);
            $summary[$projectID]->estimate += (float)$row->estimate;
            $summary[$projectID]->consumed += (float)$row->consumed;
            if(!isset($inactiveObjects["{$row->objectType}:{$row->objectID}"])) $summary[$projectID]->left += zget($leftMap, "{$row->project}:{$row->execution}:{$row->objectType}:{$row->objectID}", 0);
        }

        return $summary;
    }

    protected function getInactiveObjectMap(array $rows): array
    {
        $bugIdList   = array();
        $storyIdList = array();
        foreach($rows as $row)
        {
            if($row->objectType == 'bug') $bugIdList[(int)$row->objectID] = (int)$row->objectID;
            else $storyIdList[(int)$row->objectID] = (int)$row->objectID;
        }

        $inactive = array();
        foreach($bugIdList as $bugID) $inactive["bug:$bugID"] = true;
        foreach($storyIdList as $storyID)
        {
            $inactive["story:$storyID"] = true;
            $inactive["requirement:$storyID"] = true;
        }

        if($bugIdList)
        {
            $bugs = $this->dao->select('id, status, deleted')->from(TABLE_BUG)->where('id')->in($bugIdList)->fetchAll('id');
            foreach($bugs as $bug) if(!$bug->deleted && $bug->status != 'closed') unset($inactive["bug:{$bug->id}"]);
        }
        if($storyIdList)
        {
            $stories = $this->dao->select('id, status, deleted, type')->from(TABLE_STORY)->where('id')->in($storyIdList)->fetchAll('id');
            foreach($stories as $story)
            {
                if($story->deleted || $story->status == 'closed') continue;
                unset($inactive["story:{$story->id}"], $inactive["requirement:{$story->id}"]);
            }
        }

        return $inactive;
    }
}

<?php
$today = helper::today();
$burns = $this->dao->select("execution, '$today' AS date, sum(estimate) AS `estimate`, sum(`left`) AS `left`, SUM(consumed) AS `consumed`")->from(TABLE_TASK)
    ->where('execution')->in($executionIdList)
    ->andWhere('deleted')->eq('0')
    ->andWhere('isParent')->eq('0')
    ->andWhere('status')->ne('cancel')
    ->groupBy('execution')
    ->fetchAll('execution');

$closedLefts = $this->dao->select('execution, sum(`left`) AS `left`')->from(TABLE_TASK)
    ->where('execution')->in($executionIdList)
    ->andWhere('deleted')->eq('0')
    ->andWhere('isParent')->eq('0')
    ->andWhere('status')->eq('closed')
    ->groupBy('execution')
    ->fetchAll('execution');

$finishedEstimates = $this->dao->select("execution, sum(`estimate`) AS `estimate`")->from(TABLE_TASK)
    ->where('execution')->in($executionIdList)
    ->andWhere('deleted')->eq('0')
    ->andWhere('isParent')->eq('0')
    ->andWhere('status', true)->eq('done')
    ->orWhere('status')->eq('closed')
    ->markRight(1)
    ->groupBy('execution')
    ->fetchAll('execution');

$storyPoints = $this->dao->select('t1.project, sum(t2.estimate) AS `storyPoint`')->from(TABLE_PROJECTSTORY)->alias('t1')
    ->leftJoin(TABLE_STORY)->alias('t2')->on('t1.story = t2.id')
    ->leftJoin(TABLE_PRODUCT)->alias('t3')->on('t2.product = t3.id')
    ->where('t1.project')->in($executionIdList)
    ->andWhere('t2.deleted')->eq(0)
    ->andWhere('t2.status')->ne('closed')
    ->andWhere('t2.stage')->in('wait,planned,projected,developing')
    ->andWhere('t2.isParent')->eq('0')
    ->groupBy('project')
    ->fetchAll('project');

$objectHours = $this->loadModel('objecteffort')->getSummaryByExecution($executionIdList);
foreach($objectHours as $executionID => $hours)
{
    if(!isset($burns[$executionID]))
    {
        $burns[$executionID] = (object)array('execution' => $executionID, 'date' => $today, 'estimate' => 0, 'left' => 0, 'consumed' => 0);
    }

    $burns[$executionID]->estimate += $hours->estimate;
    $burns[$executionID]->left     += $hours->left;
    $burns[$executionID]->consumed += $hours->consumed;
}

return array($burns, $closedLefts, $finishedEstimates, $storyPoints);

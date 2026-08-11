<?php
$totalEstimate = $this->dao->select('ROUND(SUM(t1.estimate), 1) AS totalEstimate')->from(TABLE_TASK)->alias('t1')
    ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.execution = t2.id')
    ->where('t2.project')->in($projectID)
    ->andWhere('t2.deleted')->eq(0)
    ->andWhere('t1.deleted')->eq(0)
    ->andWhere('t1.parent')->lt(1)
    ->fetch('totalEstimate');

$totalConsumed = $this->dao->select('ROUND(SUM(t1.consumed), 1) AS totalConsumed')->from(TABLE_TASK)->alias('t1')
    ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.execution = t2.id')
    ->where('t2.project')->in($projectID)
    ->andWhere('t2.deleted')->eq(0)
    ->andWhere('t1.deleted')->eq(0)
    ->andWhere('t1.parent')->lt(1)
    ->fetch('totalConsumed');

$totalLeft = $this->dao->select('ROUND(SUM(t1.`left`), 1) AS totalLeft')->from(TABLE_TASK)->alias('t1')
    ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.execution = t2.id')
    ->where('t2.project')->in($projectID)
    ->andWhere('t2.deleted')->eq(0)
    ->andWhere('t1.deleted')->eq(0)
    ->andWhere('t1.parent')->lt(1)
    ->andWhere('t1.status')->ne('closed,cancel')
    ->fetch('totalLeft');

$objectHours = $this->loadModel('objecteffort')->getSummaryByProject(array($projectID));
if(isset($objectHours[$projectID]))
{
    $totalEstimate = (float)$totalEstimate + (float)$objectHours[$projectID]->estimate;
    $totalConsumed = (float)$totalConsumed + (float)$objectHours[$projectID]->consumed;
    $totalLeft     = (float)$totalLeft     + (float)$objectHours[$projectID]->left;
}

$workhour = new stdclass();
$workhour->totalHours = $this->dao->select('sum(t1.days * t1.hours) AS totalHours')->from(TABLE_TEAM)->alias('t1')
    ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.root=t2.id')
    ->leftJoin(TABLE_USER)->alias('t3')->on('t1.account=t3.account')
    ->where('t2.id')->in($projectID)
    ->andWhere('t2.deleted')->eq(0)
    ->andWhere('t1.type')->eq('project')
    ->andWhere('t3.deleted')->eq(0)
    ->fetch('totalHours');

$workhour->totalHours    = empty($workhour->totalHours) ? 0 : $workhour->totalHours;
$workhour->totalEstimate = empty($totalEstimate) ? 0 : round((float)$totalEstimate, 1);
$workhour->totalConsumed = empty($totalConsumed) ? 0 : round((float)$totalConsumed, 1);
$workhour->totalLeft     = empty($totalLeft) ? 0 : round((float)$totalLeft, 1);

return $workhour;

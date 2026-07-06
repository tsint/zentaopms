<?php
$stats       = $this->getTaskStats($projectIdList);
$teamMembers = $this->dao->select('t1.root, COUNT(1) AS members')->from(TABLE_TEAM)->alias('t1')
    ->leftJoin(TABLE_USER)->alias('t2')->on('t1.account=t2.account')
    ->where('t1.type')->eq('project')
    ->beginIF(!empty($projectIdList))->andWhere('t1.root')->in($projectIdList)->fi()
    ->andWhere('t2.deleted')->eq(0)
    ->groupBy('t1.root')
    ->fetchPairs('root');

foreach($teamMembers as $projectID => $teamCount)
{
    if(!isset($stats[$projectID])) $stats[$projectID] = array('totalEstimate' => 0, 'totalConsumed' => 0, 'totalLeft' => 0, 'teamCount' => 0, 'totalConsumedNotDel' => 0, 'totalLeftNotDel' => 0);
    $stats[$projectID]['teamCount'] = $teamCount;
}

$objectHours = $this->loadModel('objecteffort')->getSummaryByProject($projectIdList);
foreach($objectHours as $projectID => $hours)
{
    if(!isset($stats[$projectID])) $stats[$projectID] = array('totalEstimate' => 0, 'totalConsumed' => 0, 'totalLeft' => 0, 'teamCount' => 0, 'totalConsumedNotDel' => 0, 'totalLeftNotDel' => 0);

    $stats[$projectID]['totalEstimate']       += $hours->estimate;
    $stats[$projectID]['totalConsumed']       += $hours->consumed;
    $stats[$projectID]['totalLeft']           += $hours->left;
    $stats[$projectID]['totalConsumedNotDel'] += $hours->consumed;
    $stats[$projectID]['totalLeftNotDel']     += $hours->left;
}

$executionIdList = $this->dao->select('id')->from(TABLE_EXECUTION)
    ->where('project')->in($projectIdList)
    ->andWhere('deleted')->eq('0')
    ->fetchPairs('id', 'id');
$executionHours = $this->objecteffort->getSummaryByExecution(array_values($executionIdList));
foreach($executionHours as $executionID => $hours)
{
    if(!isset($stats[$executionID])) $stats[$executionID] = array('totalEstimate' => 0, 'totalConsumed' => 0, 'totalLeft' => 0, 'teamCount' => 0, 'totalConsumedNotDel' => 0, 'totalLeftNotDel' => 0);

    $stats[$executionID]['totalEstimate']       += $hours->estimate;
    $stats[$executionID]['totalConsumed']       += $hours->consumed;
    $stats[$executionID]['totalLeft']           += $hours->left;
    $stats[$executionID]['totalConsumedNotDel'] += $hours->consumed;
    $stats[$executionID]['totalLeftNotDel']     += $hours->left;
}

foreach($stats as $projectID => $project)
{
    $totalRealNotDel = $project['totalConsumedNotDel'] + $project['totalLeftNotDel'];
    $progress        = $totalRealNotDel ? floor($project['totalConsumedNotDel'] / $totalRealNotDel * 1000) / 1000 * 100 : 0;
    $this->dao->update(TABLE_PROJECT)
        ->set('progress')->eq($progress)
        ->set('teamCount')->eq($project['teamCount'])
        ->set('estimate')->eq($project['totalEstimate'])
        ->set('consumed')->eq($project['totalConsumedNotDel'])
        ->set('left')->eq($project['totalLeftNotDel'])
        ->where('id')->eq($projectID)
        ->exec();
}

return !dao::isError();

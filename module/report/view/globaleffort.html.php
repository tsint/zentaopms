<?php include '../../common/view/header.lite.html.php';?>
<?php
$geLang    = $lang->report->globalEffort;
$canExport = common::hasPriv('report', 'exportGlobalEffortCSV');
$query     = array('export' => 'csv', 'onlybody' => 'yes') + $filters;
$exportURL = $this->createLink('report', 'globalEffort') . '&' . http_build_query($query);
$riskName  = function($risk) use ($geLang) {return zget($geLang->riskList, $risk, $risk);};
?>
<style>
#globalEffortPage {padding: 16px;}
body.m-report-globaleffort > #main {height: auto !important; min-height: calc(100vh - 48px); overflow: visible;}
#globalEffortPage .effort-filter {display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 16px;}
#globalEffortPage .effort-filter .field {width: 150px;}
#globalEffortPage .effort-filter .field label {display: block; margin-bottom: 4px; color: #666; font-weight: 400;}
#globalEffortPage .stat-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 16px;}
#globalEffortPage .stat-card {padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;}
#globalEffortPage .stat-card .label {display: block; margin-bottom: 6px; color: #666;}
#globalEffortPage .stat-card .value {font-size: 20px; font-weight: 700;}
#globalEffortPage .section {max-width: 100%; margin-bottom: 16px; overflow-x: auto; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;}
#globalEffortPage .section > .section-title {padding: 10px 12px; border-bottom: 1px solid #eee; font-weight: 700;}
#globalEffortPage .risk-low {color: #229f24;}
#globalEffortPage .risk-medium {color: #b97900;}
#globalEffortPage .risk-high {color: #d93026;}
#globalEffortPage .table {margin-bottom: 0;}
#globalEffortPage .section .table {min-width: 720px;}
#globalEffortPage .effort-progress-grid {display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;}
#globalEffortPage .effort-progress-grid > * {min-width: 0;}
@media (max-width: 700px) {
  #globalEffortPage {padding: 10px;}
  #globalEffortPage .effort-filter .field {width: 100%;}
  #globalEffortPage .effort-progress-grid {grid-template-columns: 1fr; gap: 0;}
}
</style>
<div id='globalEffortPage'>
    <form method='get' class='effort-filter'>
      <?php echo html::hidden('m', 'report');?>
      <?php echo html::hidden('f', 'globalEffort');?>
      <div class='field'>
        <label><?php echo $geLang->begin;?></label>
        <?php echo html::input('begin', zget($filters, 'begin', ''), "class='form-control form-date'");?>
      </div>
      <div class='field'>
        <label><?php echo $geLang->end;?></label>
        <?php echo html::input('end', zget($filters, 'end', ''), "class='form-control form-date'");?>
      </div>
      <div class='field'>
        <label><?php echo $lang->productCommon;?></label>
        <?php echo html::select('product', $products, zget($filters, 'product', 0), "class='form-control'");?>
      </div>
      <div class='field'>
        <label><?php echo $lang->projectCommon;?></label>
        <?php echo html::select('project', $projects, zget($filters, 'project', 0), "class='form-control'");?>
      </div>
      <div class='field'>
        <label><?php echo $lang->executionCommon;?></label>
        <?php echo html::select('execution', $executions, zget($filters, 'execution', 0), "class='form-control'");?>
      </div>
      <div class='field'>
        <label><?php echo $lang->user->common;?></label>
        <?php echo html::select('account', $users, zget($filters, 'account', ''), "class='form-control'");?>
      </div>
      <div class='field'>
        <label><?php echo $geLang->objectType;?></label>
        <?php echo html::select('objectType', $geLang->objectTypeList, zget($filters, 'objectType', ''), "class='form-control'");?>
      </div>
      <div class='field'>
        <label><?php echo $geLang->dimension;?></label>
        <?php echo html::select('dimension', $geLang->dimensionList, $dimension, "class='form-control'");?>
      </div>
      <button type='submit' class='btn btn-primary'><?php echo $geLang->filter;?></button>
      <?php if($canExport):?>
      <?php echo html::a($exportURL, $geLang->exportCSV, '', "id='exportGlobalEffortCSV' class='btn btn-secondary'");?>
      <?php endif;?>
    </form>

    <div class='section'>
      <div class='section-title'><?php echo $geLang->summary;?></div>
      <div class='stat-grid'>
        <div class='stat-card'><span class='label'><?php echo $geLang->recordCount;?></span><span class='value'><?php echo $summary->records;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->consumed;?></span><span class='value'><?php echo $summary->consumed;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->productCount;?></span><span class='value'><?php echo $summary->productCount;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->projectCount;?></span><span class='value'><?php echo $summary->projectCount;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->taskCount;?></span><span class='value'><?php echo $summary->taskCount;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->requirementCount;?></span><span class='value'><?php echo $summary->requirementCount;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->activeUsers;?></span><span class='value'><?php echo $summary->userCount;?></span></div>
      </div>
    </div>

    <div class='section'>
      <div class='section-title'><?php echo $geLang->health;?></div>
      <div class='stat-grid'>
        <div class='stat-card'><span class='label'><?php echo $geLang->risk;?></span><span class='value risk-<?php echo $health->riskLevel;?>'><?php echo $riskName($health->riskLevel);?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->avgHoursPerUser;?></span><span class='value'><?php echo $health->avgHoursPerUser;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->avgHoursPerDay;?></span><span class='value'><?php echo $health->avgHoursPerDay;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->overloadDays;?></span><span class='value'><?php echo $health->overloadDays;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->topAccount;?></span><span class='value'><?php echo $health->topAccount ?: '-';?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->topAccountShare;?></span><span class='value'><?php echo round($health->topAccountShare * 100, 1);?>%</span></div>
      </div>
    </div>

    <div class='effort-progress-grid'>
      <div>
        <div class='section'>
          <div class='section-title'><?php echo $lang->productCommon . $geLang->costProgress;?></div>
          <?php include 'globaleffort.costprogress.html.php';?>
        </div>
      </div>
      <div>
        <div class='section'>
          <div class='section-title'><?php echo $lang->projectCommon . $geLang->costProgress;?></div>
          <?php $progressRows = $projectProgress; include 'globaleffort.costprogress.html.php';?>
        </div>
      </div>
    </div>

    <div class='section'>
      <div class='section-title'><?php echo $geLang->distribution;?></div>
      <table class='table table-hover table-fixed'>
        <thead><tr><th><?php echo zget($geLang->dimensionList, $dimension, $dimension);?></th><th><?php echo $geLang->recordCount;?></th><th><?php echo $geLang->consumed;?></th><th><?php echo $lang->report->percent;?></th></tr></thead>
        <tbody>
        <?php foreach($distribution as $row):?>
          <tr><td><?php echo $row->dimension;?></td><td><?php echo $row->records;?></td><td><?php echo $row->consumed;?></td><td><?php echo round($row->percent * 100, 1);?>%</td></tr>
        <?php endforeach;?>
        <?php if(empty($distribution)):?><tr><td colspan='4' class='text-center text-muted'><?php echo $geLang->empty;?></td></tr><?php endif;?>
        </tbody>
      </table>
    </div>

    <div class='section'>
      <div class='section-title'><?php echo $geLang->records;?></div>
      <table class='table table-hover table-fixed'>
        <thead><tr><th><?php echo $geLang->begin;?></th><th><?php echo $lang->productCommon;?></th><th><?php echo $lang->projectCommon;?></th><th><?php echo $lang->executionCommon;?></th><th><?php echo $geLang->objectType;?></th><th>ID</th><th><?php echo $lang->user->common;?></th><th><?php echo $geLang->consumed;?></th><th><?php echo $geLang->left;?></th><th><?php echo $lang->comment;?></th></tr></thead>
        <tbody>
        <?php foreach($records as $row):?>
          <tr><td><?php echo $row->date;?></td><td><?php echo $row->product;?></td><td><?php echo $row->project;?></td><td><?php echo $row->execution;?></td><td><?php echo $row->objectType;?></td><td><?php echo $row->objectID;?></td><td><?php echo $row->account;?></td><td><?php echo $row->consumed;?></td><td><?php echo $row->left;?></td><td title='<?php echo strip_tags((string)$row->work);?>'><?php echo helper::substr(strip_tags((string)$row->work), 20);?></td></tr>
        <?php endforeach;?>
        <?php if(empty($records)):?><tr><td colspan='10' class='text-center text-muted'><?php echo $geLang->empty;?></td></tr><?php endif;?>
        </tbody>
      </table>
      <div class='table-footer'><?php $pager->show('right', 'pagerjs');?></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function()
{
    var pageContainer = document.getElementById('globalEffortPage');
    var mainContainer = document.querySelector('body > #main');
    if(pageContainer && mainContainer && pageContainer.parentElement !== mainContainer) mainContainer.appendChild(pageContainer);

    var exportButton = document.getElementById('exportGlobalEffortCSV');
    if(!exportButton) return;

    exportButton.addEventListener('click', function(e)
    {
        e.preventDefault();
        const url = this.href;
        fetch(url, {credentials: 'include', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(response => response.blob().then(blob => ({response, blob})))
            .then(({response, blob}) =>
            {
                const disposition = response.headers.get('Content-Disposition') || '';
                const matched = disposition.match(/filename="?([^";]+)"?/);
                const fileName = matched ? decodeURIComponent(matched[1]) : 'global_effort.csv';
                const downloadURL = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadURL;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(downloadURL);
            });
    });
});
</script>
<?php include '../../common/view/footer.lite.html.php';?>

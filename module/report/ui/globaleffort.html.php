<?php
declare(strict_types = 1);
namespace zin;

$geLang     = $lang->report->globalEffort;
$colors     = $geLang->objectTypeColors;
$canExport  = common::hasPriv('report', 'exportGlobalEffortCSV');
$exportArgs = array('export' => 'csv', 'onlybody' => 'yes') + $detailFilters;
$exportURL  = $this->createLink('report', 'globalEffort') . '&' . http_build_query($exportArgs);
$dateRange  = zget($filters, 'dateRange', (!empty($filters['begin']) || !empty($filters['end']) ? 'custom' : 'all'));
$maxStale   = 0;
$maxAccount = 0;
foreach($staleObjects as $object) $maxStale = max($maxStale, (int)$object->staleDays);
foreach($accountStack as $account) $maxAccount = max($maxAccount, (float)$account->total);

$value = function($array, $key, $default = '') {return zget($array, $key, $default);};
$color = function($type) use ($colors) {return zget($colors, $type, '#6b7280');};
$hiddenFilters = function($filters)
{
    $html = '';
    foreach($filters as $key => $filter)
    {
        if(is_array($filter)) continue;
        $html .= \html::hidden($key, (string)$filter);
    }
    return $html;
};

ob_start();
?>
<style>
#globalEffortPage {padding: 16px; color: #1f2933;}
#globalEffortPage .toolbar {display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px;}
#globalEffortPage .filter-block {background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 12px;}
#globalEffortPage .filter-title {font-weight: 700; margin-bottom: 10px;}
#globalEffortPage .field-grid {display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px;}
#globalEffortPage .field label {display: block; margin-bottom: 4px; color: #5f6b7a; font-weight: 400;}
#globalEffortPage .field-full {grid-column: 1 / -1;}
#globalEffortPage .actions {display: flex; gap: 8px; align-items: center; margin-bottom: 16px;}
#globalEffortPage .panel-grid {display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px;}
#globalEffortPage .panel {background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;}
#globalEffortPage .panel-title {padding: 10px 12px; border-bottom: 1px solid #eee; font-weight: 700;}
#globalEffortPage .panel-body {padding: 12px;}
#globalEffortPage .stat-grid {display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 8px;}
#globalEffortPage .stat-card {border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; min-height: 74px;}
#globalEffortPage .stat-card .label {display: block; color: #5f6b7a; margin-bottom: 6px;}
#globalEffortPage .stat-card .value {font-size: 22px; font-weight: 700; line-height: 1.2;}
#globalEffortPage .stack-bar {display: flex; height: 18px; overflow: hidden; background: #eef2f7; border-radius: 4px;}
#globalEffortPage .stack-segment {min-width: 2px; height: 100%;}
#globalEffortPage .legend {display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 10px;}
#globalEffortPage .legend-item {display: inline-flex; align-items: center; gap: 6px; color: #4b5563;}
#globalEffortPage .swatch {width: 10px; height: 10px; border-radius: 2px; display: inline-block;}
#globalEffortPage .stale-row, #globalEffortPage .account-row {display: grid; grid-template-columns: 150px 1fr 60px; gap: 10px; align-items: center; margin-bottom: 10px;}
#globalEffortPage .bar-track {height: 16px; background: #eef2f7; border-radius: 4px; overflow: hidden;}
#globalEffortPage .bar-fill {height: 100%; background: #d97706;}
#globalEffortPage .account-stack {display: flex; height: 18px; width: 100%; overflow: hidden; background: #eef2f7; border-radius: 4px;}
#globalEffortPage .muted {color: #6b7280;}
#globalEffortPage .detail-filter {display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; align-items: end; margin-bottom: 12px;}
#globalEffortPage .table {margin-bottom: 0;}
#globalEffortPage .table-wrap {overflow-x: auto;}
#globalEffortPage .table-wrap .table {min-width: 960px;}
#globalEffortPage details summary {cursor: pointer; color: #2563eb;}
#globalEffortPage details .work-body {white-space: pre-wrap; margin-top: 6px; color: #374151;}
@media (max-width: 900px) {
  #globalEffortPage .toolbar, #globalEffortPage .panel-grid {grid-template-columns: 1fr;}
  #globalEffortPage .stat-grid {grid-template-columns: repeat(2, minmax(0, 1fr));}
  #globalEffortPage .detail-filter {grid-template-columns: 1fr 1fr;}
}
@media (max-width: 560px) {
  #globalEffortPage {padding: 10px;}
  #globalEffortPage .field-grid, #globalEffortPage .stat-grid, #globalEffortPage .detail-filter {grid-template-columns: 1fr;}
  #globalEffortPage .stale-row, #globalEffortPage .account-row {grid-template-columns: 90px 1fr 46px;}
}
</style>
<div id='globalEffortPage'>
  <form method='get'>
    <?php echo \html::hidden('m', 'report');?>
    <?php echo \html::hidden('f', 'globalEffort');?>
    <div class='toolbar'>
      <div class='filter-block'>
        <div class='filter-title'><?php echo $geLang->timeFilter;?></div>
        <div class='field-grid'>
          <div class='field field-full'><label><?php echo $geLang->dateRange;?></label><?php echo \html::select('dateRange', $geLang->dateRangeList, $dateRange, "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->begin;?></label><?php echo \html::input('begin', $value($filters, 'begin'), "class='form-control form-date'");?></div>
          <div class='field'><label><?php echo $geLang->end;?></label><?php echo \html::input('end', $value($filters, 'end'), "class='form-control form-date'");?></div>
        </div>
      </div>
      <div class='filter-block'>
        <div class='filter-title'><?php echo $geLang->spaceFilter;?></div>
        <div class='field-grid'>
          <div class='field'><label><?php echo $geLang->productLine;?></label><?php echo \html::select('productLine', $productLines, $value($filters, 'productLine', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $lang->productCommon;?></label><?php echo \html::select('product', $products, $value($filters, 'product', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->program;?></label><?php echo \html::select('program', $programs, $value($filters, 'program', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $lang->projectCommon;?></label><?php echo \html::select('project', $projects, $value($filters, 'project', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $lang->executionCommon;?></label><?php echo \html::select('execution', $executions, $value($filters, 'execution', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->objectType;?></label><?php echo \html::select('objectType', $geLang->objectTypeList, $value($filters, 'objectType'), "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->objectID;?></label><?php echo \html::input('objectID', $value($filters, 'objectID'), "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->includeRelated;?></label><?php echo \html::checkbox('includeRelated', array(1 => $geLang->includeRelated), !empty($filters['includeRelated']) ? 1 : 0);?></div>
        </div>
      </div>
      <div class='filter-block'>
        <div class='filter-title'><?php echo $geLang->peopleFilter;?></div>
        <div class='field-grid'>
          <div class='field'><label><?php echo $geLang->dept;?></label><?php echo \html::select('dept', $depts, $value($filters, 'dept', 0), "class='form-control'");?></div>
          <div class='field'><label><?php echo $geLang->team;?></label><?php echo \html::input('team', $value($filters, 'team'), "class='form-control'");?></div>
          <div class='field field-full'><label><?php echo $geLang->member;?></label><?php echo \html::select('account', $users, is_array($value($filters, 'account')) ? '' : $value($filters, 'account'), "class='form-control'");?></div>
          <div class='field field-full'><label><?php echo $geLang->staleDays;?></label><?php echo \html::input('staleDays', $value($filters, 'staleDays', 5), "class='form-control'");?></div>
        </div>
      </div>
    </div>
    <div class='actions'>
      <button type='submit' class='btn btn-primary'><?php echo $geLang->filter;?></button>
      <?php if($canExport):?>
      <?php echo \html::a($exportURL, $geLang->exportCSV, '', "id='exportGlobalEffortCSV' class='btn btn-secondary'");?>
      <?php endif;?>
    </div>
  </form>

  <div class='panel'>
    <div class='panel-title'><?php echo $geLang->management;?></div>
    <div class='panel-body'>
      <div class='stat-grid'>
        <div class='stat-card'><span class='label'><?php echo $geLang->totalConsumed;?></span><span class='value'><?php echo $management->totalConsumed;?>h</span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->estimatedRatio;?></span><span class='value'><?php echo $management->estimatedTotal > 0 ? round($management->estimatedPercent * 100, 1) . '%' : $geLang->notEstimated;?></span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->estimatedConsumed;?></span><span class='value'><?php echo $management->estimatedConsumed;?>h</span></div>
        <div class='stat-card'><span class='label'><?php echo $geLang->estimatedTotal;?></span><span class='value'><?php echo $management->estimatedTotal;?>h</span></div>
      </div>
    </div>
  </div>

  <div class='panel-grid'>
    <div class='panel'>
      <div class='panel-title'><?php echo $geLang->objectDistribution;?></div>
      <div class='panel-body'>
        <div class='stack-bar'>
          <?php foreach($objectTypes as $type):?>
          <div class='stack-segment' title='<?php echo $type->label . ' ' . $type->consumed . 'h';?>' style='width: <?php echo max(1, round($type->percent * 100, 2));?>%; background: <?php echo $color($type->type);?>'></div>
          <?php endforeach;?>
        </div>
        <div class='legend'>
          <?php foreach($objectTypes as $type):?>
          <span class='legend-item'><i class='swatch' style='background: <?php echo $color($type->type);?>'></i><?php echo $type->label;?> <?php echo $type->consumed;?>h</span>
          <?php endforeach;?>
          <?php if(empty($objectTypes)):?><span class='muted'><?php echo $geLang->empty;?></span><?php endif;?>
        </div>
      </div>
    </div>
    <div class='panel'>
      <div class='panel-title'><?php echo $geLang->staleObjects;?></div>
      <div class='panel-body'>
        <?php foreach($staleObjects as $object):?>
        <div class='stale-row'>
          <div><?php echo zget($geLang->objectTypeList, $object->objectType, $object->objectType) . ' #' . $object->objectID;?></div>
          <div class='bar-track'><div class='bar-fill' style='width: <?php echo $maxStale > 0 ? round($object->staleDays / $maxStale * 100, 2) : 0;?>%'></div></div>
          <div><?php echo $object->staleDays;?>天</div>
        </div>
        <?php endforeach;?>
        <?php if(empty($staleObjects)):?><div class='muted'><?php echo $geLang->empty;?></div><?php endif;?>
      </div>
    </div>
  </div>

  <div class='panel'>
    <div class='panel-title'><?php echo $geLang->accountStack;?></div>
    <div class='panel-body'>
      <?php foreach($accountStack as $account):?>
      <div class='account-row'>
        <div><?php echo zget($users, $account->account, $account->account);?></div>
        <div class='account-stack' style='width: <?php echo $maxAccount > 0 ? max(6, round($account->total / $maxAccount * 100, 2)) : 0;?>%'>
          <?php foreach($account->segments as $segment):?>
          <div class='stack-segment' title='<?php echo $segment->label . ' ' . $segment->consumed . 'h';?>' style='width: <?php echo max(1, round($segment->percent * 100, 2));?>%; background: <?php echo $color($segment->type);?>'></div>
          <?php endforeach;?>
        </div>
        <div><?php echo $account->total;?>h</div>
      </div>
      <?php endforeach;?>
      <?php if(empty($accountStack)):?><div class='muted'><?php echo $geLang->empty;?></div><?php endif;?>
    </div>
  </div>

  <div class='panel'>
    <div class='panel-title'><?php echo $geLang->records;?></div>
    <div class='panel-body'>
      <form method='get' class='detail-filter'>
        <?php echo \html::hidden('m', 'report') . \html::hidden('f', 'globalEffort') . $hiddenFilters($filters);?>
        <div class='field'><label><?php echo $geLang->detailDate;?></label><?php echo \html::input('detailDate', $value($detailFilters, 'detailDate'), "class='form-control form-date'");?></div>
        <div class='field'><label><?php echo $geLang->detailAccount;?></label><?php echo \html::select('detailAccount', $users, $value($detailFilters, 'detailAccount'), "class='form-control'");?></div>
        <div class='field'><label><?php echo $geLang->detailProduct;?></label><?php echo \html::select('detailProduct', $products, $value($detailFilters, 'detailProduct', 0), "class='form-control'");?></div>
        <div class='field'><label><?php echo $geLang->detailProject;?></label><?php echo \html::select('detailProject', $projects, $value($detailFilters, 'detailProject', 0), "class='form-control'");?></div>
        <button type='submit' class='btn btn-primary'><?php echo $geLang->filter;?></button>
      </form>
      <div class='table-wrap'>
        <table class='table table-hover table-fixed'>
          <thead><tr><th><?php echo $geLang->begin;?></th><th><?php echo $lang->productCommon;?></th><th><?php echo $lang->projectCommon;?></th><th><?php echo $lang->executionCommon;?></th><th><?php echo $geLang->objectType;?></th><th>ID</th><th><?php echo $lang->user->common;?></th><th><?php echo $geLang->consumed;?></th><th><?php echo $geLang->left;?></th><th><?php echo $geLang->workContent;?></th></tr></thead>
          <tbody>
          <?php foreach($records as $row):?>
            <tr>
              <td><?php echo $row->date;?></td>
              <td><?php echo zget($products, $row->product, $row->product);?></td>
              <td><?php echo zget($projects, $row->project, $row->project);?></td>
              <td><?php echo zget($executions, $row->execution, $row->execution);?></td>
              <td><?php echo zget($geLang->objectTypeList, $row->objectType, $row->objectType);?></td>
              <td><?php echo $row->objectID;?></td>
              <td><?php echo zget($users, $row->account, $row->account);?></td>
              <td><?php echo $row->consumed;?></td>
              <td><?php echo $row->left;?></td>
              <td><details><summary><?php echo \helper::substr(strip_tags((string)$row->work), 20);?></summary><div class='work-body'><?php echo nl2br(htmlspecialchars((string)$row->work));?></div></details></td>
            </tr>
          <?php endforeach;?>
          <?php if(empty($records)):?><tr><td colspan='10' class='text-center text-muted'><?php echo $geLang->empty;?></td></tr><?php endif;?>
          </tbody>
        </table>
      </div>
      <div class='table-footer'><?php $pager->show('right', 'pagerjs');?></div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function()
{
    const dimensionDropmenu = document.querySelector('#heading #dropmenu[data-fetcher*="m=dimension"], #heading #dropmenu[data-fetcher*="module=report"][data-fetcher*="method=globaleffort"]');
    if(dimensionDropmenu) dimensionDropmenu.remove();

    const exportButton = document.getElementById('exportGlobalEffortCSV');
    if(!exportButton) return;

    exportButton.addEventListener('click', function(e)
    {
        e.preventDefault();
        fetch(this.href, {credentials: 'include', headers: {'X-Requested-With': 'XMLHttpRequest'}})
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
<?php
html(ob_get_clean());

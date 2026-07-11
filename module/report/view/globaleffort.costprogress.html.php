<?php $progressRows = isset($progressRows) ? $progressRows : $productProgress;?>
<table class='table table-hover table-fixed'>
  <thead>
    <tr>
      <th><?php echo $geLang->scope;?></th>
      <th><?php echo $geLang->objects;?></th>
      <th><?php echo $geLang->consumed;?></th>
      <th><?php echo $geLang->left;?></th>
      <th><?php echo $geLang->progress;?></th>
      <th><?php echo $geLang->overrunObjects;?></th>
      <th><?php echo $geLang->risk;?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($progressRows as $row):?>
    <tr>
      <td><?php echo $row->scopeID;?></td>
      <td><?php echo $row->objects;?></td>
      <td><?php echo $row->consumed;?></td>
      <td><?php echo $row->left;?></td>
      <td><?php echo round($row->progress * 100, 1);?>%</td>
      <td><?php echo $row->overrunObjects;?></td>
      <td class='risk-<?php echo $row->riskLevel;?>'><?php echo $riskName($row->riskLevel);?></td>
    </tr>
    <?php endforeach;?>
    <?php if(empty($progressRows)):?><tr><td colspan='7' class='text-center text-muted'><?php echo $geLang->empty;?></td></tr><?php endif;?>
  </tbody>
</table>

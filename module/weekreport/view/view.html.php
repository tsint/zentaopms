<?php
/**
 * The view detail file of weekreport module of ZenTaoPMS.
 *
 * 采用 header.lite 轻量模板（不渲染框架 chrome），全屏展示 xls 文件。
 * 前端用 SheetJS 读取 xls 二进制并渲染（保留合并单元格等格式）。
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @package     weekreport
 * @link        https://www.zentao.net
 */
include $app->getModuleRoot() . 'common/view/header.lite.html.php';

/* 引入 SheetJS，并把 xls 的 web 路径传给前端。 */
js::import($jsRoot . 'sheetjs/xlsx.full.min.js');
js::set('xlsUrl', $downloadUrl);
?>
<div id="weekreportScreen">
  <div class="detail-head">
    <h2><?php echo $record->name;?></h2>
    <a class="download-btn" href="<?php echo $downloadUrl;?>" download="<?php echo $record->name;?>">下载原文件</a>
  </div>
  <div id="excelView" class="excel-wrap">
    <div class="excel-loading">加载中…</div>
  </div>
</div>
<script>
$(function()
{
    fetch(xlsUrl)
        .then(function(r) { return r.arrayBuffer(); })
        .then(function(buf)
        {
            var wb  = XLSX.read(buf, {type: 'array'});
            var ws  = wb.Sheets[wb.SheetNames[0]];
            var html = XLSX.utils.sheet_to_html(ws, {editable: false, id: 'xlsTable'});
            $('#excelView').html(html);

            /* 冻结前两列：第二列 left = 第一列实际宽度。 */
            var firstColWidth = $('#xlsTable td:first-child').outerWidth() || 0;
            $('#xlsTable td:nth-child(2)').css('left', firstColWidth + 'px');
        })
        .catch(function()
        {
            $('#excelView').html('<div class="excel-error">文件加载失败，请<a href="' + xlsUrl + '" download>下载</a>后查看。</div>');
        });
});
</script>
<?php include $app->getModuleRoot() . 'common/view/footer.lite.html.php';?>

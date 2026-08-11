/**
 * 按搜索条件重载周报列表。
 * Reload weekreport list by search conditions.
 *
 * @access public
 * @return void
 */
function weekreportSearch()
{
    /* encodeURIComponent 防止文件名含中文/空格/&/= 等字符时破坏 URL（createLink 不对值编码）。 */
    var name  = encodeURIComponent($('#weekreportSearchbar [name="name"]').val() || '');
    var begin = ($('#weekreportSearchbar [name="begin"]').val() || '').replaceAll('-', '');
    var link  = $.createLink('weekreport', 'browse', 'name=' + name + '&begin=' + begin);
    loadPage(link);
}

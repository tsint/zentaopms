<?php
global $app, $lang;
$app->loadLang('objecteffort');
if(common::hasPriv('objecteffort', 'record'))
{
    $title = $lang->objecteffort->record;
    $base  = helper::createLink('objecteffort', 'record', 'objectType=bug&objectID={id}');
    $allowed = null;
    if(isset($bugs))
    {
        $allowed = array();
        foreach($bugs as $bug) if(empty($bug->deleted) && $bug->status != 'closed') $allowed[(int)$bug->id] = true;
    }
    $allowedJSON = json_encode($allowed);
    echo <<<EOT
<script>
(function()
{
    if(!window.waitDom && !window.$) return;
    var allowedObjectEffort = {$allowedJSON};
    var injectObjectEffort = function()
    {
        $('#bugs a[href*="bug-view-"], #bugs a[href*="m=bug"][href*="f=view"]').each(function()
        {
            var href = $(this).attr('href') || '';
            var match = href.match(/bug-view-(\\d+)/) || href.match(/[?&]bugID=(\\d+)/);
            if(!match) return;
            var id = match[1];
            if(allowedObjectEffort && !allowedObjectEffort[id]) return;
            var link = '{$base}'.replace('%7Bid%7D', id).replace('{id}', id);
            var \$cell = $(this).closest('[data-col], td');
            if(!\$cell.length || \$cell.find('.objecteffort-record').length) return;
            \$cell.append(" <a class='objecteffort-record text-primary' data-toggle='modal' data-size='lg' href='" + link + "' title='{$title}'><i class='icon icon-time'></i></a>");
        });
    };

    var observeObjectEffort = function()
    {
        injectObjectEffort();
        var target = document.getElementById('bugs');
        if(target && window.MutationObserver) new MutationObserver(injectObjectEffort).observe(target, {childList: true, subtree: true});
    };
    observeObjectEffort();
    [50, 200, 500, 1000].forEach(function(delay){window.setTimeout(observeObjectEffort, delay);});
    if(window.waitDom) window.waitDom('#bugs a[href*="bug-view-"], #bugs a[href*="m=bug"][href*="f=view"]', observeObjectEffort);
})();
</script>
EOT;
}

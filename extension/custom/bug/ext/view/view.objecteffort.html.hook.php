<?php
namespace zin;

global $app, $lang;
$app->loadLang('objecteffort');
$showObjectEffort = !$bug->deleted && $bug->status != 'closed' && common::hasPriv('objecteffort', 'record');
if($showObjectEffort)
{
    $effortLink  = createLink('objecteffort', 'record', "objectType=bug&objectID={$bug->id}");
    $buttonTitle = $lang->objecteffort->record;
    echo <<<EOT
<script>
(function()
{
    if(!window.waitDom && !window.\$) return;
    var injectObjectEffort = function()
    {
        var \$toolbar = $('.detail-header .toolbar, .actions-menu, .detail-actions, .detail-toolbar, .toolbar').first();
        if(!\$toolbar.length || \$toolbar.find('.objecteffort-record').length) return;
        \$toolbar.append("<a class='btn ghost objecteffort-record' data-toggle='modal' data-size='lg' href='{$effortLink}' title='{$buttonTitle}'><i class='icon icon-time'></i> {$buttonTitle}</a>");
    };
    if(window.waitDom) window.waitDom('.detail-header .toolbar, .actions-menu, .detail-actions, .detail-toolbar, .toolbar', injectObjectEffort);
    else \$(injectObjectEffort);
})();
</script>
EOT;
}

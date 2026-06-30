<?php
global $app, $lang;
$app->loadLang('objecteffort');
if(common::hasPriv('objecteffort', 'record'))
{
    $title = $lang->objecteffort->record;
    $base  = helper::createLink('objecteffort', 'record', 'objectType={type}&objectID={id}');
    $allowed = null;
    if(isset($stories))
    {
        $allowed = array();
        $collectAllowed = function($items) use (&$collectAllowed, &$allowed)
        {
            foreach($items as $story)
            {
                if(!is_object($story)) continue;
                if(empty($story->deleted) && $story->status != 'closed') $allowed[(int)$story->id] = $story->type == 'requirement' ? 'requirement' : 'story';
                if(!empty($story->children)) $collectAllowed($story->children);
            }
        };
        $collectAllowed($stories);
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
        $('.dtable a[href*="story-view-"], .dtable a[href*="requirement-view-"], .dtable a[href*="m=story"][href*="f=view"], .dtable a[href*="m=requirement"][href*="f=view"]').each(function()
        {
            var href = $(this).attr('href') || '';
            var match = href.match(/(?:story|requirement)-view-(\\d+)/) || href.match(/[?&]storyID=(\\d+)/);
            if(!match) return;
            var id = match[1];
            if(allowedObjectEffort && !allowedObjectEffort[id]) return;
            var objectType = allowedObjectEffort && allowedObjectEffort[id] ? allowedObjectEffort[id] : 'story';
            var link = '{$base}'.replace('%7Btype%7D', objectType).replace('{type}', objectType).replace('%7Bid%7D', id).replace('{id}', id);
            var \$cell = $(this).closest('[data-col], td');
            if(!\$cell.length || \$cell.find('.objecteffort-record').length) return;
            \$cell.append(" <a class='objecteffort-record text-primary' data-toggle='modal' data-size='lg' href='" + link + "' title='{$title}'><i class='icon icon-time'></i></a>");
        });
    };

    var observeObjectEffort = function()
    {
        injectObjectEffort();
        var target = document.getElementById('stories') || document.querySelector('.dtable');
        if(target && window.MutationObserver) new MutationObserver(injectObjectEffort).observe(target, {childList: true, subtree: true});
    };
    observeObjectEffort();
    [50, 200, 500, 1000].forEach(function(delay){window.setTimeout(observeObjectEffort, delay);});
    if(window.waitDom) window.waitDom('.dtable a[href*="story-view-"], .dtable a[href*="requirement-view-"], .dtable a[href*="m=story"][href*="f=view"], .dtable a[href*="m=requirement"][href*="f=view"]', observeObjectEffort);
})();
</script>
EOT;
}

<?php
global $app;

$workflowType   = isset($story->type) && in_array($story->type, array('epic', 'requirement'), true) ? $story->type : 'story';
$workflowStatus = isset($story->status) ? (string)$story->status : '';
$model = $app->control->loadModel('workflowflowchart');
echo $model->renderFlowHtml($workflowType, $workflowStatus, true);

$definition = $model->getDefinition($workflowType);
if(!empty($definition['enabled']))
{
    $mermaidSource = $model->renderMermaid($workflowType, $definition);
    if($mermaidSource)
    {
        $escape = function($value){return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');};
        echo '<section class="workflowflowchart-detail" style="margin-top:12px">';
        echo '<div class="workflowflowchart-head"><div class="workflowflowchart-title">' . $escape($model->lang->workflowflowchart->common) . ' (Mermaid)</div></div>';
        echo '<div class="workflow-mermaid-panel"><div class="workflow-mermaid"><pre class="mermaid">' . $escape($mermaidSource) . '</pre></div></div>';
        echo '<script>if(window.mermaid) mermaid.run(); else if(window.zui && zui.require) zui.require("mermaid").then(function(){mermaid.run();});</script>';
        echo '</section>';
    }
}

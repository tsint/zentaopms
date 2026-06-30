<?php
$normalizedAction = strtolower($actionType);
if(in_array(strtolower($objectType), array('bug', 'story', 'requirement')) && in_array($normalizedAction, array('closed', 'activated', 'deleted')))
{
    $this->loadModel('objecteffort')->refreshObjectStatistics($objectType, (int)$objectID);
}

<?php
$workflowOldBug = $this->getById((int)$bug->id);
$workflowComment = isset($bug->comment) ? (string)$bug->comment : (isset($this->post->comment) ? (string)$this->post->comment : '');
if($workflowOldBug && !$this->loadModel('workflowflowchart')->checkTransition('bug', (int)$bug->id, $workflowOldBug->status, 'active', 'activate', $workflowComment)) return false;

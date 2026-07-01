<?php
declare(strict_types=1);

class workflowflowchart extends control
{
    public function manage(string $objectType = 'story')
    {
        return $this->browse($objectType, 'edit');
    }

    public function browse(string $objectType = 'story', string $mode = 'edit')
    {
        $this->loadModel('workflowflowchart');
        $objectType = strtolower($objectType);
        if(!$this->workflowflowchart->isAvailableObjectType($objectType)) $objectType = 'story';
        $editable = $mode != 'view' && $this->app->user->admin;
        if(!$editable && !$this->app->user->admin && !common::hasPriv('workflowflowchart', 'browse')) return $this->deny();

        if(!empty($_POST))
        {
            if(!$editable) return $this->send(array('result' => 'fail', 'message' => $this->lang->workflowflowchart->error->adminOnly));
            $definition = json_decode((string)$this->post->definition, true);
            if(!is_array($definition)) return $this->send(array('result' => 'fail', 'message' => $this->lang->workflowflowchart->error->invalidJSON));
            if(!$this->workflowflowchart->saveDefinition($objectType, $definition) || dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true));
        }

        if($this->app->user->admin) $this->loadModel('admin')->setMenu();
        $this->app->loadLang('user');
        $this->view->title       = $this->lang->workflowflowchart->common;
        $this->view->objectType  = $objectType;
        $this->view->objectTypes = $this->workflowflowchart->getAvailableObjectTypes();
        $this->view->editable    = $editable;
        $this->view->definition  = $this->workflowflowchart->getDefinition($objectType);
        $this->view->statusList  = $this->workflowflowchart->getStatusList($objectType);
        $this->view->actionList  = $this->workflowflowchart->getActionList($objectType);
        $this->view->roleList    = $this->lang->user->roleList;
        $this->view->users       = $this->loadModel('user')->getPairs('noletter|noclosed');
        $this->display();
    }

    protected function deny()
    {
        return $this->send(array('result' => 'fail', 'message' => $this->lang->workflowflowchart->error->denied));
    }
}

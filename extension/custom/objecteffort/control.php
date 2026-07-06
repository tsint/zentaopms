<?php
declare(strict_types=1);

class objecteffort extends control
{
    public function record(string $objectType, int $objectID, int $recTotal = 0, int $recPerPage = 10, int $pageID = 1)
    {
        $this->loadModel('objecteffort');
        if(!$this->objecteffort->canRecord($objectType, $objectID)) return $this->send(array('result' => 'fail', 'message' => $this->lang->objecteffort->error->denied));

        if(!empty($_POST))
        {
            if(isset($_POST['date']) && is_array($_POST['date']))
            {
                $workhour = form::batchData($this->config->objecteffort->form->recordBatch)->get();
                foreach($workhour as $record)
                {
                    if(isset($_POST['execution']) && !is_array($_POST['execution'])) $record->execution = (int)$_POST['execution'];
                    if(isset($_POST['project']) && !is_array($_POST['project']))     $record->project   = (int)$_POST['project'];
                }
                $idList   = $this->objecteffort->recordBatch($objectType, $objectID, $workhour);
                if(dao::isError() || $idList === false) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            }
            else
            {
                $data = form::data($this->config->objecteffort->form->record)->get();
                $id   = $this->objecteffort->record($objectType, $objectID, $data);
                if(dao::isError() || !$id) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            }

            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true));
        }

        $this->app->loadClass('pager', true);
        $pager = new pager($recTotal, 10, $pageID, 'objectEffort');
        if($pager->recPerPage != 10)
        {
            $pager->recPerPage = 10;
            $pager->setPageTotal();
            $pager->setPageID($pageID);
        }

        $this->view->objectType = $objectType;
        $this->view->objectID   = $objectID;
        $this->view->object     = $this->objecteffort->getObject($objectType, $objectID);
        $this->view->summary    = $this->objecteffort->getSummary($objectType, $objectID);
        $this->view->efforts    = $this->objecteffort->getList($objectType, $objectID, 0, $pager);
        $this->view->pager      = $pager;
        $this->view->executions = $this->objecteffort->getExecutionPairs($objectType, $objectID);
        $this->view->projects   = $this->objecteffort->getProjectPairs($objectType, $objectID);
        $this->view->users      = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    public function edit(int $effortID)
    {
        $this->loadModel('objecteffort');
        $effort = $this->objecteffort->getByID($effortID);
        if(!$effort || !$this->objecteffort->canOperate($effort, 'edit')) return $this->send(array('result' => 'fail', 'message' => $this->lang->objecteffort->error->denied));

        if(!empty($_POST))
        {
            $data = form::data($this->config->objecteffort->form->edit)->get();
            $changes = $this->objecteffort->update($effortID, $data);
            if($changes === false || dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true));
        }

        $this->view->effort = $effort;
        $this->view->executions = $this->objecteffort->getExecutionPairs($effort->objectType, (int)$effort->objectID);
        $this->view->projects   = $this->objecteffort->getProjectPairs($effort->objectType, (int)$effort->objectID);
        $this->display();
    }

    public function delete(int $effortID)
    {
        $this->loadModel('objecteffort');
        $effort = $this->objecteffort->getByID($effortID);
        if(!$effort || !$this->objecteffort->canOperate($effort, 'delete')) return $this->send(array('result' => 'fail', 'message' => $this->lang->objecteffort->error->denied));

        $deleted = $this->objecteffort->deleteEffort($effortID);
        if(!$deleted || dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

        return $this->send(array('result' => 'success', 'message' => $this->lang->deleteSuccess, 'load' => true));
    }
}

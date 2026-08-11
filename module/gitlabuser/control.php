<?php
declare(strict_types=1);
/**
 * The control file of gitlabuser module of ZenTaoPMS.
 *
 * 维护「GitLab 用户名 → 禅道账号」映射表：列表 / 新增 / 修改 / 删除。
 *
 * @package    gitlabuser
 */
class gitlabuser extends control
{
    /**
     * 映射列表。
     * Browse gitlab user mappings.
     *
     * @param  string $orderBy
     * @access public
     * @return void
     */
    public function browse(string $orderBy = 'id_desc')
    {
        $this->view->title       = $this->lang->gitlabuser->common;
        $this->view->orderBy     = $orderBy;
        $this->view->users       = $this->loadModel('user')->getPairs('noletter|nodeleted');
        $this->view->gitlabUsers = $this->gitlabuser->getList($orderBy);
        $this->display();
    }

    /**
     * 新增映射。
     * Create a gitlab user mapping.
     *
     * @access public
     * @return void
     */
    public function create()
    {
        if($_POST)
        {
            $data = form::data($this->config->gitlabuser->form->create)
                ->add('createdBy', $this->app->user->account)
                ->get();

            if($this->gitlabuser->isGitlabAccountExists($data->gitlabAccount)) return $this->sendError(sprintf($this->lang->gitlabuser->accountExists, $data->gitlabAccount));

            $createID = $this->gitlabuser->create($data);
            if(dao::isError()) return $this->sendError(dao::getError());

            $this->loadModel('action')->create('gitlabuser', $createID, 'created');
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true, 'closeModal' => true));
        }

        $this->view->title = $this->lang->gitlabuser->create;
        $this->view->users = $this->loadModel('user')->getPairs('noletter|nodeleted|noclosed');
        $this->display();
    }

    /**
     * 编辑映射。
     * Edit a gitlab user mapping.
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function edit(int $id)
    {
        if($_POST)
        {
            $data = form::data($this->config->gitlabuser->form->edit)
                ->add('editedBy', $this->app->user->account)
                ->get();

            if($this->gitlabuser->isGitlabAccountExists($data->gitlabAccount, $id)) return $this->sendError(sprintf($this->lang->gitlabuser->accountExists, $data->gitlabAccount));

            $changes = $this->gitlabuser->update($id, $data);
            if(dao::isError()) return $this->sendError(dao::getError());

            if($changes)
            {
                $actionID = $this->loadModel('action')->create('gitlabuser', $id, 'Edited');
                $this->action->logHistory($actionID, $changes);
            }

            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true, 'closeModal' => true));
        }

        $this->view->title      = $this->lang->gitlabuser->editAction;
        $this->view->gitlabUser = $this->gitlabuser->fetchByID($id);
        $this->view->users      = $this->loadModel('user')->getPairs('noletter|nodeleted|noclosed');
        $this->display();
    }

    /**
     * 删除映射（软删除）。
     * Delete a gitlab user mapping.
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function delete(int $id)
    {
        $this->gitlabuser->delete(TABLE_GITLABUSER, $id);
        if(dao::isError()) return $this->sendError(dao::getError());

        return $this->send(array('result' => 'success', 'message' => $this->lang->deleteSuccess, 'load' => true));
    }
}

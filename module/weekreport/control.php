<?php
/**
 * The control file of weekreport module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2025 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(https://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     weekreport
 * @link        https://www.zentao.net
 */
class weekreport extends control
{
    /**
     * Construct.
     *
     * @param  string $moduleName
     * @param  string $methodName
     * @param  string $appName
     * @access public
     * @return void
     */
    public function __construct(string $moduleName = '', string $methodName = '', string $appName = '')
    {
        parent::__construct($moduleName, $methodName, $appName);
    }

    /**
     * 周报列表（支持文件名称和导入时间搜索）。
     * Browse weekreport list with file name and import time search.
     *
     * @param  string $name
     * @param  string $begin
     * @param  string $orderBy
     * @param  int    $recTotal
     * @param  int    $recPerPage
     * @param  int    $pageID
     * @access public
     * @return void
     */
    public function browse(string $name = '', string $begin = '', string $orderBy = 'id_desc', int $recTotal = 0, int $recPerPage = 20, int $pageID = 1)
    {
        if(!common::hasPriv('weekreport', 'browse')) $this->loadModel('common')->deny('weekreport', 'browse');

        /* 归一化日期（兼容 20260730 与 2026-07-30 两种格式）。 */
        if($begin and strtotime($begin)) $begin = date('Y-m-d', strtotime($begin));

        $this->app->loadClass('pager', true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        $records = $this->weekreport->getList($name, $begin, $orderBy, $pager);
        $records = $this->weekreportZen->prepareBrowseList($records);

        $this->view->title   = $this->lang->weekreport->common;
        $this->view->records = $records;
        $this->view->pager   = $pager;
        $this->view->orderBy = $orderBy;
        $this->view->name    = $name;
        $this->view->begin   = $begin;
        $this->view->users   = $this->loadModel('user')->getPairs('noletter');

        $this->display();
    }

    /**
     * 导入周报 xls。
     * Import a weekreport xls file.
     *
     * @access public
     * @return void
     */
    public function import()
    {
        if(!common::hasPriv('weekreport', 'import')) $this->loadModel('common')->deny('weekreport', 'import');

        if($this->server->request_method == 'POST')
        {
            $file = $this->loadModel('file')->getUpload('file');
            if(empty($_FILES))  return $this->send(array('result' => 'fail', 'message' => $this->lang->file->errorFileFormat));
            if(empty($file[0])) return $this->send(array('result' => 'fail', 'message' => $this->lang->file->errorFileFormat));

            $file      = $file[0];
            $extension = strtolower($file['extension']);
            if(!in_array($extension, $this->config->weekreport->allowedExtensions))
                return $this->send(array('result' => 'fail', 'message' => $this->lang->excel->canNotRead));

            /* 校验文件大小。 */
            $maxSize = $this->config->weekreport->maxUploadSize * 1024 * 1024;
            if($file['size'] > $maxSize)
                return $this->send(array('result' => 'fail', 'message' => sprintf($this->lang->weekreport->fileTooLarge, $this->config->weekreport->maxUploadSize)));

            /* 物理保存（保留扩展名，确定性相对路径，便于跨月读取）。 */
            $companyID = isset($this->app->company->id) ? $this->app->company->id : 1;
            $ym        = date('Ym/');
            $absDir    = $this->app->getAppRoot() . "www/data/upload/{$companyID}/" . $ym;
            if(!is_dir($absDir))
            {
                mkdir($absDir, 0777, true);
                touch($absDir . 'index.html');
            }
            $savedName = 'wr' . date('dHis') . mt_rand(1000, 9999) . '.' . $extension;
            $absPath   = $absDir . $savedName;
            if(!move_uploaded_file($file['tmpname'], $absPath))
                return $this->send(array('result' => 'fail', 'message' => $this->lang->file->uploadError[1]));

            /* 校验可读性并解析表头。 */
            $this->app->loadClass('phpexcel', true);
            if(!phpExcel::canRead($absPath))
            {
                @unlink($absPath);
                return $this->send(array('result' => 'fail', 'message' => $this->lang->excel->canNotRead));
            }

            /* 解析原始行（不限表头格式），推导元数据。 */
            $rows = $this->weekreport->readExcelRows($absPath);
            $meta = $this->weekreport->deriveMeta($rows);
            /* 同名文件覆盖：已存在则更新记录 + 替换物理文件，否则新建。 */
            $exist    = $this->weekreport->getByName($file['title']);
            $fileData = array(
                'pathname'    => $ym . $savedName,
                'extension'   => $extension,
                'size'        => $file['size'],
                'year'        => $meta['year'],
                'beginDate'   => $meta['beginDate'],
                'endDate'     => $meta['endDate'],
                'recordCount' => $meta['recordCount'],
            );
            if($exist)
            {
                $this->weekreport->unlinkFile($exist);
                $this->weekreport->updateByID($exist->id, $fileData);
                $recordID = $exist->id;
            }
            else
            {
                $fileData['name'] = $file['title'];
                $recordID = $this->weekreport->create($fileData);
            }

            $this->loadModel('action')->create('weekreport', $recordID, 'imported');
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true, 'closeModal' => true));
        }

        $this->view->title = $this->lang->weekreport->import;
        $this->display();
    }

    /**
     * 删除周报记录（含物理文件）。
     * Delete a weekreport record and its physical file.
     *
     * @param  int $id
     * @access public
     * @return void
     */
    public function delete(int $id)
    {
        if(!common::hasPriv('weekreport', 'delete')) $this->loadModel('common')->deny('weekreport', 'delete');

        $record = $this->weekreport->getById($id);
        if(empty($record)) return $this->send(array('result' => 'fail', 'message' => $this->lang->weekreport->fileNotFound));

        $this->weekreport->deleteByID($id);
        $this->weekreport->unlinkFile($record);
        if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

        $this->loadModel('action')->create('weekreport', $id, 'deleted');
        return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true));
    }

    /**
     * 查看详情：读取 xls 原始内容并全屏展示（header.lite 模板，无框架 chrome）。
     * View details: read xls raw content and show fullscreen (lite template, without chrome).
     *
     * @param  int    $id
     * @access public
     * @return void
     */
    public function view(int $id)
    {
        if(!common::hasPriv('weekreport', 'view')) $this->loadModel('common')->deny('weekreport', 'view');

        $record = $this->weekreport->getById($id);
        if(empty($record)) return $this->send(array('result' => 'fail', 'message' => $this->lang->weekreport->fileNotFound));
        if(!$this->weekreport->fileExists($record)) return $this->send(array('result' => 'fail', 'message' => $this->lang->weekreport->fileNotFound));

        /* 原样读取 xls 全部行列（含表头）。 */
        $rows = $this->weekreport->readExcelRows($this->weekreport->getRealPath($record));

        /* xls 的 web 路径，供前端 SheetJS 读取渲染。 */
        $companyID = isset($this->app->company->id) ? $this->app->company->id : 1;
        $downloadUrl = $this->app->getWebRoot() . "data/upload/{$companyID}/" . $record->pathname;

        $this->view->title       = $this->lang->weekreport->screenTitle . ' - ' . $record->name;
        $this->view->record      = $record;
        $this->view->rows        = $rows;
        $this->view->downloadUrl = $downloadUrl;

        $this->display();
    }
}

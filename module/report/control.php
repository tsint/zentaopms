<?php
/**
 * The control file of report module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2023 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.cnezsoft.com)
 * @license     ZPL(http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     report
 * @version     $Id: control.php 4622 2013-03-28 01:09:02Z chencongzhi520@gmail.com $
 * @link        https://www.zentao.net
 */
class report extends control
{
    /**
     * 项目ID。
     * The projectID.
     *
     * @var float
     * @access public
     */
    public $projectID = 0;

    /**
     * 构造函数。
     * Construct.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 报告主页，跳转到年度数据。
     * The index of report, goto aunnual data.
     *
     * @access public
     * @return void
     */
    public function index()
    {
        $this->locate(inlink('annualData'));
    }

    /**
     * 发送每日提醒邮件。
     * Send daily reminder mail.
     *
     * @access public
     * @return void
     */
    public function remind()
    {
        /* Get reminder, and send email. */
        $reminder = $this->reportZen->getReminder();

        /* Check mail turnon, if the system doesn't turn on the e-mail function, return the tip. */
        $this->loadModel('mail');
        if(!$this->config->mail->turnon)
        {
            echo "You should turn on the Email feature first.\n";
            return false;
        }

        foreach($reminder as $user => $mail)
        {
            /* Reset $this->output. */
            $this->clear();

            $mailTitle  = $this->lang->report->mailTitle->begin;
            $mailTitle .= isset($mail->bugs)      ? sprintf($this->lang->report->mailTitle->bug,      count($mail->bugs))      : '';
            $mailTitle .= isset($mail->tasks)     ? sprintf($this->lang->report->mailTitle->task,     count($mail->tasks))     : '';
            $mailTitle .= isset($mail->todos)     ? sprintf($this->lang->report->mailTitle->todo,     count($mail->todos))     : '';
            $mailTitle .= isset($mail->testTasks) ? sprintf($this->lang->report->mailTitle->testTask, count($mail->testTasks)) : '';
            $mailTitle .= isset($mail->cards)     ? sprintf($this->lang->report->mailTitle->card,     count($mail->cards))     : '';
            $mailTitle  = rtrim($mailTitle, ',');

            /* Get email content and title.*/
            $this->view->mail      = $mail;
            $this->view->mailTitle = $mailTitle;

            $oldViewType = $this->viewType;
            if($oldViewType == 'json') $this->viewType = 'html';
            $mailContent    = $this->parse('report', 'dailyreminder');
            $this->viewType = $oldViewType;

            /* Send email.*/
            echo date('Y-m-d H:i:s') . " sending to {$user}, ";
            $this->mail->send($user, $mailTitle, $mailContent, '', true);
            if($this->mail->isError())
            {
                echo "fail: \n" ;
                a($this->mail->getError());
            }
            echo "ok\n";
        }
    }

    /**
     * 展示年度数据。
     * Show annual data.
     *
     * @param  string $year
     * @param  string $dept
     * @param  string $account
     * @access public
     * @return void
     */
    public function annualData(string $year = '', string $dept = '', string $account = '')
    {
        $this->app->loadLang('story');
        $this->app->loadLang('task');
        $this->app->loadLang('bug');
        $this->app->loadLang('testcase');

        /* Assign annual data. */
        $this->reportZen->assignAnnualReport($year, $dept, $account);

        $mode = 'company';
        if((int)$dept && empty($account)) $mode = 'dept';
        if($account) $mode = 'user';

        $this->view->contributionCountTips = $this->report->getContributionCountTips($mode);

        $this->view->mode    = $mode;
        $this->view->account = $account;
        $this->display();
    }

    /**
     * Global effort statistics and risk analysis.
     *
     * @access public
     * @return void
     */
    public function globalEffort()
    {
        $query = array();
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        $filters       = $this->reportZen->buildGlobalEffortFilters();
        $detailFilters = $this->reportZen->buildGlobalEffortDetailFilters($filters);
        if(isset($query['export']) && $query['export'] == 'csv')
        {
            if(!common::hasPriv('report', 'exportGlobalEffortCSV')) $this->loadModel('common')->deny('report', 'exportGlobalEffortCSV');

            $csv = $this->report->buildGlobalEffortCSV($detailFilters);
            return $this->fetch('file', 'sendDownHeader', array('fileName' => 'global_effort_' . date('Ymd_His'), 'fileType' => 'csv', 'content' => $csv));
        }

        $this->app->loadClass('pager', true);
        $pager = new pager(isset($query['recTotal']) ? (int)$query['recTotal'] : 0, !empty($query['recPerPage']) ? (int)$query['recPerPage'] : 20, !empty($query['pageID']) ? (int)$query['pageID'] : 1);

        $this->view->title           = $this->lang->report->globalEffort->common;
        unset($this->lang->switcherMenu);
        $this->view->filters         = $filters;
        $this->view->detailFilters   = $detailFilters;
        $this->view->summary         = $this->report->getGlobalEffortSummary($filters);
        $this->view->health          = $this->report->getGlobalEffortHealth($filters);
        $this->view->management      = $this->report->getGlobalEffortManagementMetrics($filters);
        $this->view->objectTypes     = $this->report->getGlobalEffortObjectTypeDistribution($filters);
        $this->view->staleObjects    = $this->report->getGlobalEffortStaleObjects($filters);
        $this->view->accountStack    = $this->report->getGlobalEffortAccountStack($filters);
        $this->view->productProgress = $this->report->getGlobalEffortCostProgress('product', $filters);
        $this->view->projectProgress = $this->report->getGlobalEffortCostProgress('project', $filters);
        $this->view->records         = $this->report->getGlobalEffortRecords($detailFilters, $pager);
        $this->view->pager           = $pager;
        $this->view->productLines    = array(0 => $this->lang->all) + $this->dao->select('id,name')->from(TABLE_MODULE)->where('type')->eq('line')->andWhere('deleted')->eq('0')->orderBy('`order`')->fetchPairs();
        $this->view->programs        = array(0 => $this->lang->all) + $this->dao->select('id,name')->from(TABLE_PROJECT)->where('type')->eq('program')->andWhere('deleted')->eq('0')->fetchPairs();
        $this->view->products        = array(0 => $this->lang->all) + $this->dao->select('id,name')->from(TABLE_PRODUCT)->where('deleted')->eq('0')->fetchPairs();
        $this->view->projects        = array(0 => $this->lang->all) + $this->dao->select('id,name')->from(TABLE_PROJECT)->where('type')->eq('project')->andWhere('deleted')->eq('0')->fetchPairs();
        $this->view->executions      = array(0 => $this->lang->all) + $this->dao->select('id,name')->from(TABLE_EXECUTION)->where('type')->in('sprint,stage,kanban')->andWhere('deleted')->eq('0')->fetchPairs();
        $this->view->users           = array('' => $this->lang->all) + $this->loadModel('user')->getPairs('noletter|nodeleted');
        $this->view->depts           = array(0 => $this->lang->all) + $this->loadModel('dept')->getOptionMenu();

        $this->display();
    }

    /**
     * Export global effort records as CSV.
     *
     * @access public
     * @return void
     */
    public function exportGlobalEffortCSV()
    {
        if(!common::hasPriv('report', 'exportGlobalEffortCSV')) $this->loadModel('common')->deny('report', 'exportGlobalEffortCSV');

        $filters = $this->reportZen->buildGlobalEffortDetailFilters($this->reportZen->buildGlobalEffortFilters());
        $csv     = $this->report->buildGlobalEffortCSV($filters);
        $this->fetch('file', 'sendDownHeader', array('fileName' => 'global_effort_' . date('Ymd_His'), 'fileType' => 'csv', 'content' => $csv));
    }
}

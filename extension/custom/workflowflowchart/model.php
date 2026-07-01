<?php
declare(strict_types=1);

class workflowflowchartModel extends model
{
    protected static $definitionCache = array();
    protected static $tableAvailable = null;

    public function isValidObjectType(string $objectType): bool
    {
        return in_array(strtolower($objectType), $this->config->workflowflowchart->objectTypes, true);
    }

    public function getAvailableObjectTypes(): array
    {
        $types = $this->config->workflowflowchart->objectTypes;
        if(empty($this->config->enableER)) $types = array_values(array_diff($types, array('epic')));
        if(empty($this->config->URAndSR))  $types = array_values(array_diff($types, array('requirement')));
        return $types;
    }

    public function isAvailableObjectType(string $objectType): bool
    {
        return in_array(strtolower($objectType), $this->getAvailableObjectTypes(), true);
    }

    public function getStatusList(string $objectType): array
    {
        $objectType = strtolower($objectType);
        if($objectType == 'bug')
        {
            $this->app->loadLang('bug');
            $source = $this->lang->bug->statusList;
            $keys   = array('active', 'resolved', 'closed');
        }
        elseif($objectType == 'task')
        {
            $this->app->loadLang('task');
            $source = $this->lang->task->statusList;
            $keys   = array('wait', 'doing', 'done', 'pause', 'cancel', 'closed');
        }
        elseif($objectType == 'testcase')
        {
            $this->app->loadLang('testcase');
            $source = $this->lang->testcase->statusList;
            $keys   = array('wait', 'normal', 'blocked', 'investigate');
        }
        else
        {
            $this->app->loadLang($objectType == 'requirement' ? 'requirement' : ($objectType == 'epic' ? 'epic' : 'story'));
            $source = $this->lang->story->statusList;
            $keys   = array('draft', 'reviewing', 'active', 'changing', 'closed');
        }

        $list = array();
        foreach($keys as $key) $list[$key] = isset($source[$key]) ? $source[$key] : $key;
        return $list;
    }

    public function getActionList(string $objectType): array
    {
        $this->app->loadLang('workflowflowchart');
        $actions = $this->config->workflowflowchart->actions->{strtolower($objectType)};
        $list = array();
        foreach($actions as $action) $list[$action] = isset($this->lang->workflowflowchart->actionList[$action]) ? $this->lang->workflowflowchart->actionList[$action] : $action;
        return $list;
    }

    public function getEntryStates(string $objectType): array
    {
        $objectType = strtolower($objectType);
        if(in_array($objectType, array('epic', 'requirement', 'story'), true)) return array('draft');
        if($objectType == 'bug') return array('active');
        if($objectType == 'task') return array('wait');
        if($objectType == 'testcase') return array('normal');
        return array();
    }

    public function getDefaultDefinition(string $objectType): array
    {
        $objectType = strtolower($objectType);
        $statuses   = $this->getStatusList($objectType);
        $nodes      = array();
        $index      = 0;
        foreach($statuses as $status => $label)
        {
            $nodes[] = array('id' => $status, 'status' => $status, 'label' => $label, 'x' => 120 + ($index % 3) * 240, 'y' => 100 + (int)floor($index / 3) * 190);
            $index++;
        }

        $edges = array();
        $add = function(string $source, string $target, string $action) use (&$edges)
        {
            $edges[] = array(
                'id' => $source . '-' . $target . '-' . $action,
                'source' => $source,
                'target' => $target,
                'action' => $action,
                'label' => $this->getActionListForDefault($action),
                'roles' => array(),
                'accounts' => array(),
                'requireComment' => false,
                'enabled' => true,
                'description' => ''
            );
        };

        if($objectType == 'bug')
        {
            $add('active', 'resolved', 'resolve');
            $add('resolved', 'closed', 'close');
            $add('active', 'closed', 'close');
            $add('resolved', 'active', 'activate');
            $add('closed', 'active', 'activate');
        }
        elseif($objectType == 'task')
        {
            $add('wait', 'doing', 'start');
            $add('pause', 'doing', 'restart');
            $add('doing', 'pause', 'pause');
            $add('wait', 'done', 'finish');
            $add('doing', 'done', 'finish');
            $add('pause', 'done', 'finish');
            $add('wait', 'cancel', 'cancel');
            $add('doing', 'cancel', 'cancel');
            $add('pause', 'cancel', 'cancel');
            $add('done', 'closed', 'close');
            $add('cancel', 'closed', 'close');
            $add('closed', 'doing', 'activate');
            $add('done', 'doing', 'activate');
            $add('cancel', 'doing', 'activate');
        }
        elseif($objectType == 'testcase')
        {
            $add('normal', 'wait', 'submitreview');
            $add('normal', 'wait', 'edit');
            $add('wait', 'normal', 'review');
            $add('normal', 'blocked', 'block');
            $add('blocked', 'normal', 'activate');
            $add('normal', 'investigate', 'investigate');
            $add('investigate', 'normal', 'activate');
        }
        else
        {
            $add('draft', 'reviewing', 'submitreview');
            $add('draft', 'active', 'review');
            $add('reviewing', 'active', 'review');
            $add('reviewing', 'draft', 'review');
            $add('reviewing', 'changing', 'review');
            $add('reviewing', 'closed', 'review');
            $add('reviewing', 'draft', 'recallreview');
            $add('reviewing', 'changing', 'recallreview');
            $add('active', 'changing', 'change');
            $add('changing', 'reviewing', 'submitreview');
            $add('changing', 'active', 'review');
            $add('changing', 'active', 'recallchange');
            $add('draft', 'closed', 'close');
            $add('reviewing', 'closed', 'close');
            $add('active', 'closed', 'close');
            $add('changing', 'closed', 'close');
            $add('closed', 'active', 'activate');
            $add('closed', 'draft', 'activate');
        }

        return array('objectType' => $objectType, 'enabled' => false, 'nodes' => $nodes, 'edges' => $edges, 'version' => 1);
    }

    protected function getActionListForDefault(string $action): string
    {
        $this->app->loadLang('workflowflowchart');
        return isset($this->lang->workflowflowchart->actionList[$action]) ? $this->lang->workflowflowchart->actionList[$action] : $action;
    }

    public function renderMermaid(string $objectType, array $definition = array()): string
    {
        $objectType = strtolower($objectType);
        if(!$this->isValidObjectType($objectType)) return '';
        if(empty($definition)) $definition = $this->getDefinition($objectType);

        $statusList = $this->getStatusList($objectType);
        $actionList = $this->getActionList($objectType);
        $lines      = array('stateDiagram-v2');
        $stateIDs   = array();

        foreach($definition['nodes'] as $node)
        {
            $status = (string)$node['status'];
            $id     = $this->getMermaidStateID($status);
            $label  = isset($statusList[$status]) ? $statusList[$status] : (isset($node['label']) ? (string)$node['label'] : $status);
            $stateIDs[$status] = $id;
            $lines[] = '    state "' . $this->escapeMermaidLabel($label) . '" as ' . $id;
        }

        foreach($this->getEntryStates($objectType) as $status)
        {
            if(isset($stateIDs[$status])) $lines[] = '    [*] --> ' . $stateIDs[$status];
        }

        $transitions = array();
        foreach($definition['edges'] as $edge)
        {
            if(isset($edge['enabled']) && !$edge['enabled']) continue;
            $source = (string)$edge['source'];
            $target = (string)$edge['target'];
            if(!isset($stateIDs[$source]) || !isset($stateIDs[$target])) continue;

            $action = isset($edge['action']) ? (string)$edge['action'] : '';
            $label  = !empty($edge['label']) ? (string)$edge['label'] : (isset($actionList[$action]) ? $actionList[$action] : $action);
            $key    = $source . "\t" . $target;
            if(!isset($transitions[$key])) $transitions[$key] = array('source' => $source, 'target' => $target, 'labels' => array());
            if(!in_array($label, $transitions[$key]['labels'], true)) $transitions[$key]['labels'][] = $label;
        }

        foreach($transitions as $transition)
        {
            $lines[] = '    ' . $stateIDs[$transition['source']] . ' --> ' . $stateIDs[$transition['target']] . ': ' . $this->escapeMermaidLabel(implode(' / ', $transition['labels']));
        }

        return implode("\n", $lines);
    }

    protected function getMermaidStateID(string $status): string
    {
        $id = preg_replace('/[^A-Za-z0-9_]/', '_', $status);
        if($id === '' || preg_match('/^[0-9]/', $id)) $id = 'state_' . $id;
        return $id;
    }

    protected function escapeMermaidLabel(string $label): string
    {
        $label = str_replace(array("\\", '"'), array('\\\\', '\\"'), $label);
        return trim(preg_replace('/\s+/', ' ', $label));
    }

    public function validateDefinition(string $objectType, array $definition): bool|string
    {
        $objectType = strtolower($objectType);
        if(!$this->isValidObjectType($objectType)) return 'invalidObjectType';
        if(!isset($definition['nodes']) || !is_array($definition['nodes'])) return 'invalidNodes';
        if(!isset($definition['edges']) || !is_array($definition['edges'])) return 'invalidEdges';

        $validStatuses = array_keys($this->getStatusList($objectType));
        $validActions  = $this->config->workflowflowchart->actions->$objectType;
        $nodeIDs       = array();
        $nodeStatuses  = array();
        foreach($definition['nodes'] as $node)
        {
            if(!is_array($node) || empty($node['id']) || empty($node['status'])) return 'invalidNode';
            if(isset($nodeIDs[$node['id']])) return 'duplicateNode';
            if(!in_array($node['status'], $validStatuses, true) && !preg_match('/^[a-z][a-z0-9_]{1,29}$/', (string)$node['status'])) return 'invalidStatus';
            if($node['id'] !== $node['status'] || isset($nodeStatuses[$node['status']])) return 'invalidNode';
            $nodeIDs[$node['id']] = true;
            $nodeStatuses[$node['status']] = true;
        }
        foreach($this->getEntryStates($objectType) as $entryState)
        {
            if(!isset($nodeStatuses[$entryState])) return 'missingEntryState';
        }

        $edgeIDs = array();
        $routes  = array();
        foreach($definition['edges'] as $edge)
        {
            if(!is_array($edge) || empty($edge['id']) || empty($edge['source']) || empty($edge['target'])) return 'invalidEdge';
            if(isset($edgeIDs[$edge['id']])) return 'duplicateEdge';
            if(!isset($nodeIDs[$edge['source']]) || !isset($nodeIDs[$edge['target']])) return 'invalidEndpoint';
            $action = strtolower(isset($edge['action']) ? (string)$edge['action'] : '');
            if(!in_array($action, $validActions, true)) return 'invalidAction';
            $edgeIDs[$edge['id']] = true;
            if(!isset($edge['enabled']) || $edge['enabled'])
            {
                $route = $edge['source'] . '|' . $edge['target'] . '|' . $action;
                if(isset($routes[$route])) return 'duplicateTransition';
                $routes[$route] = true;
            }
        }
        return true;
    }

    public function normalizeDefinition(string $objectType, array $definition): array
    {
        $normalized = array(
            'objectType' => strtolower($objectType),
            'enabled' => !empty($definition['enabled']),
            'nodes' => array(),
            'edges' => array(),
            'version' => isset($definition['version']) ? max(1, (int)$definition['version']) : 1
        );
        foreach($definition['nodes'] as $node)
        {
            $normalized['nodes'][] = array(
                'id' => trim((string)$node['id']),
                'status' => trim((string)$node['status']),
                'label' => trim(isset($node['label']) ? (string)$node['label'] : (string)$node['status']),
                'x' => isset($node['x']) ? (float)$node['x'] : 0,
                'y' => isset($node['y']) ? (float)$node['y'] : 0
            );
        }
        foreach($definition['edges'] as $edge)
        {
            $roles    = isset($edge['roles']) && is_array($edge['roles']) ? $edge['roles'] : array();
            $accounts = isset($edge['accounts']) && is_array($edge['accounts']) ? $edge['accounts'] : array();
            $roles    = array_values(array_unique(array_filter(array_map('trim', $roles), 'strlen')));
            $accounts = array_values(array_unique(array_filter(array_map('trim', $accounts), 'strlen')));
            $normalized['edges'][] = array(
                'id' => trim((string)$edge['id']),
                'source' => trim((string)$edge['source']),
                'target' => trim((string)$edge['target']),
                'action' => strtolower(trim((string)$edge['action'])),
                'label' => trim(isset($edge['label']) ? (string)$edge['label'] : (string)$edge['action']),
                'roles' => $roles,
                'accounts' => $accounts,
                'requireComment' => !empty($edge['requireComment']),
                'enabled' => !isset($edge['enabled']) || !empty($edge['enabled']),
                'description' => trim(isset($edge['description']) ? strip_tags((string)$edge['description']) : '')
            );
        }
        return $normalized;
    }

    public function checkDefinitionTransition(array $definition, string $source, string $target, string $action, string $role, string $account, string $comment = ''): bool|string
    {
        if(empty($definition['enabled'])) return true;
        $action = strtolower($action);
        foreach($definition['edges'] as $edge)
        {
            if(isset($edge['enabled']) && !$edge['enabled']) continue;
            if($edge['source'] != $source || $edge['target'] != $target || strtolower($edge['action']) != $action) continue;

            $roles    = isset($edge['roles']) && is_array($edge['roles']) ? $edge['roles'] : array();
            $accounts = isset($edge['accounts']) && is_array($edge['accounts']) ? $edge['accounts'] : array();
            if(($roles || $accounts) && !in_array($role, $roles, true) && !in_array($account, $accounts, true)) return 'actorDenied';
            if(!empty($edge['requireComment']) && trim(strip_tags($comment)) === '') return 'commentRequired';
            return true;
        }
        return 'transitionDenied';
    }

    public function isDefinitionActionAllowed(array $definition, string $source, string $action, string $role, string $account): bool
    {
        if(empty($definition['enabled'])) return true;
        $action = strtolower($action);
        foreach($definition['edges'] as $edge)
        {
            if(isset($edge['enabled']) && !$edge['enabled']) continue;
            if($edge['source'] != $source || strtolower($edge['action']) != $action) continue;
            $roles    = isset($edge['roles']) && is_array($edge['roles']) ? $edge['roles'] : array();
            $accounts = isset($edge['accounts']) && is_array($edge['accounts']) ? $edge['accounts'] : array();
            if(!$roles && !$accounts) return true;
            if(in_array($role, $roles, true) || in_array($account, $accounts, true)) return true;
        }
        return false;
    }

    public function isActionAllowed(string $objectType, string $source, string $action): bool
    {
        $definition = $this->getDefinition($objectType);
        return $this->isDefinitionActionAllowed($definition, $source, $action, (string)$this->app->user->role, (string)$this->app->user->account);
    }

    public function tableExists(): bool
    {
        if(self::$tableAvailable !== null) return self::$tableAvailable;
        $table = trim(TABLE_WORKFLOWFLOWCHART, '`');
        $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ' . $this->dbh->quote($table) . ' LIMIT 1';
        self::$tableAvailable = (bool)$this->dbh->query($sql)->fetchColumn();
        return self::$tableAvailable;
    }

    public function getDefinition(string $objectType): array
    {
        $objectType = strtolower($objectType);
        if(isset(self::$definitionCache[$objectType])) return self::$definitionCache[$objectType];
        $default = $this->getDefaultDefinition($objectType);
        if(!$this->tableExists()) return self::$definitionCache[$objectType] = $default;

        $row = $this->dao->select('*')->from(TABLE_WORKFLOWFLOWCHART)->where('objectType')->eq($objectType)->fetch();
        if(!$row) return self::$definitionCache[$objectType] = $default;
        $definition = json_decode($row->definition, true);
        if(!is_array($definition) || $this->validateDefinition($objectType, $definition) !== true) return self::$definitionCache[$objectType] = $default;
        $definition['enabled'] = $row->enabled == '1';
        $definition['version'] = (int)$row->version;
        self::$definitionCache[$objectType] = $definition;
        return $definition;
    }

    public function saveDefinition(string $objectType, array $definition): bool
    {
        if(!$this->app->user->admin)
        {
            dao::$errors['priv'] = $this->lang->workflowflowchart->error->adminOnly;
            return false;
        }
        $result = $this->validateDefinition($objectType, $definition);
        if($result !== true)
        {
            dao::$errors['definition'] = $this->getErrorMessage($result);
            return false;
        }
        if(!$this->tableExists())
        {
            dao::$errors['definition'] = $this->lang->workflowflowchart->error->notInstalled;
            return false;
        }

        $definition = $this->normalizeDefinition($objectType, $definition);
        $old = $this->dao->select('*')->from(TABLE_WORKFLOWFLOWCHART)->where('objectType')->eq($objectType)->fetch();
        $data = new stdclass();
        $data->objectType = $objectType;
        $data->name = $this->lang->workflowflowchart->objectTypeList[$objectType];
        $data->enabled = $definition['enabled'] ? '1' : '0';
        $data->version = $old ? (int)$old->version + 1 : 1;
        $definition['version'] = $data->version;
        $data->definition = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data->editedBy = $this->app->user->account;
        $data->editedDate = helper::now();

        if($old)
        {
            $this->dao->update(TABLE_WORKFLOWFLOWCHART)->data($data)->where('id')->eq($old->id)->exec();
        }
        else
        {
            $data->createdBy = $this->app->user->account;
            $data->createdDate = helper::now();
            $this->dao->insert(TABLE_WORKFLOWFLOWCHART)->data($data)->exec();
        }
        if(dao::isError()) return false;

        self::$definitionCache[$objectType] = $definition;
        $this->loadModel('action')->create('workflowflowchart', $old ? (int)$old->id : (int)$this->dao->lastInsertID(), $old ? 'edited' : 'opened', '', $objectType);
        return !dao::isError();
    }

    public function checkTransition(string $objectType, int $objectID, string $source, string $target, string $action, string $comment = ''): bool
    {
        $objectType = strtolower($objectType);
        $definition = $this->getDefinition($objectType);
        $result = $this->checkDefinitionTransition($definition, $source, $target, $action, (string)$this->app->user->role, (string)$this->app->user->account, $comment);
        if($result === true) return true;

        dao::$errors['workflowflowchart'] = $this->getErrorMessage($result, $source, $target);
        return false;
    }

    public function renderFlowHtml(string $objectType, string $currentStatus = '', bool $showManageLink = true): string
    {
        $objectType = strtolower($objectType);
        if(!$this->isAvailableObjectType($objectType)) return '';

        $this->app->loadLang('workflowflowchart');
        $definition    = $this->getDefinition($objectType);
        $statusList    = $this->getStatusList($objectType);
        $mermaidSource = $this->renderMermaid($objectType, $definition);
        if($mermaidSource === '') return '';

        $escape = function($value): string {return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');};
        $currentLabel = isset($statusList[$currentStatus]) ? $statusList[$currentStatus] : $currentStatus;
        $webRoot      = $this->app->getWebRoot();
        $html  = '<section class="workflowflowchart-detail" data-current-status="' . $escape($currentStatus) . '">';
        $html .= '<style>.workflowflowchart-detail{margin-top:16px;padding:16px;border:1px solid #d8dee8;border-radius:6px;background:#fff}.workflowflowchart-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.workflowflowchart-title{font-size:16px;font-weight:600;color:#1f2d3d}.workflowflowchart-current{color:#5f6f86}.workflowflowchart-mermaid{min-height:220px;overflow:auto;text-align:center}.workflowflowchart-mermaid svg{max-width:100%;height:auto}.workflowflowchart-manage{white-space:nowrap}</style>';
        $html .= '<div class="workflowflowchart-head"><div><div class="workflowflowchart-title">' . $escape($this->lang->workflowflowchart->common) . '</div>';
        if($currentStatus !== '') $html .= '<div class="workflowflowchart-current">' . $escape($this->lang->workflowflowchart->currentStatus) . ': ' . $escape($currentLabel) . '</div>';
        $html .= '</div>';
        if($showManageLink && $this->app->user->admin)
        {
            $link = helper::createLink('workflowflowchart', 'browse', "objectType={$objectType}&mode=edit");
            $html .= '<a class="btn ghost workflowflowchart-manage" href="' . $escape($link) . '"><i class="icon icon-flow"></i> ' . $escape($this->lang->workflowflowchart->configure) . '</a>';
        }
        $html .= '</div><div class="workflowflowchart-mermaid"><pre class="mermaid">' . $escape($mermaidSource) . '</pre></div>';
        $html .= '<script>(function(){function draw(){if(!window.mermaid)return;var nodes=Array.prototype.filter.call(document.querySelectorAll(".workflowflowchart-mermaid pre.mermaid"),function(node){return node.getAttribute("data-processed")!=="true"&&node.getAttribute("data-rendering")!=="1";});if(!nodes.length)return;nodes.forEach(function(node){node.setAttribute("data-rendering","1");});window.mermaid.initialize({startOnLoad:false,securityLevel:"loose"});Promise.resolve(window.mermaid.run({nodes:nodes})).catch(function(){nodes.forEach(function(node){node.removeAttribute("data-rendering");});});}function schedule(){draw();window.setTimeout(draw,100);window.setTimeout(draw,400);}if(window.mermaid){schedule();return;}var script=document.getElementById("workflowflowchart-mermaid-js");if(!script){script=document.createElement("script");script.id="workflowflowchart-mermaid-js";script.src="' . $escape($webRoot) . 'js/zui3/mermaid/mermaid.min.js";document.head.appendChild(script);}script.addEventListener("load",function(){script.dataset.loaded="1";schedule();},{once:true});if(script.dataset.loaded==="1")schedule();})();</script></section>';
        return $html;
    }

    protected function renderLegacyFlowHtml(string $objectType, string $currentStatus = '', bool $showManageLink = true): string
    {
        $objectType = strtolower($objectType);
        if(!$this->isValidObjectType($objectType)) return '';

        $this->app->loadLang('workflowflowchart');
        $definition = $this->getDefinition($objectType);
        $statusList = $this->getStatusList($objectType);
        $actionList = $this->getActionList($objectType);
        $escape = function($value): string
        {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $nodes = array();
        foreach($definition['nodes'] as $node)
        {
            $status = (string)$node['status'];
            $nodes[(string)$node['id']] = array(
                'id'     => (string)$node['id'],
                'status' => $status,
                'label'  => isset($statusList[$status]) ? $statusList[$status] : (isset($node['label']) ? (string)$node['label'] : $status)
            );
        }

        $enabledEdges = array_values(array_filter($definition['edges'], function($edge)
        {
            return !isset($edge['enabled']) || $edge['enabled'];
        }));
        $edgesBySource = array();
        foreach($enabledEdges as $edge)
        {
            $source = (string)$edge['source'];
            if(!isset($edgesBySource[$source])) $edgesBySource[$source] = array();
            $edgesBySource[$source][] = $edge;
        }

        $html  = '<section class="workflowflowchart-detail">';
        $html .= '<style>';
        $html .= '.workflowflowchart-detail{margin-top:16px;padding:16px;border:1px solid #d8dee8;border-radius:6px;background:#fff}.workflowflowchart-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.workflowflowchart-title{font-size:16px;font-weight:600;color:#1f2d3d}.workflowflowchart-current{color:#5f6f86}.workflowflowchart-board{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px}.workflowflowchart-column{border:1px solid #d8dee8;border-radius:6px;background:#fbfcff;min-width:0}.workflowflowchart-node{padding:9px 10px;border-bottom:1px solid #e7ebf2;font-weight:600;color:#1f2d3d}.workflowflowchart-node.current{background:#eaf2ff;color:#1f6feb}.workflowflowchart-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:8px;padding:8px;border:1px solid #e2e7f0;border-radius:4px;background:#fff;color:#26364d}.workflowflowchart-target{font-weight:600}.workflowflowchart-action{display:inline-block;padding:1px 6px;border-radius:9px;background:#eef3ff;color:#2468f2;font-size:12px;white-space:nowrap}.workflowflowchart-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:12px}.workflowflowchart-item{padding:8px 10px;border:1px solid #d8dee8;border-radius:4px;background:#fff;color:#26364d}.workflowflowchart-empty{padding:12px;color:#758198}.workflowflowchart-manage{white-space:nowrap}';
        $html .= '</style>';
        $html .= '<div class="workflowflowchart-head"><div><div class="workflowflowchart-title">' . $escape($this->lang->workflowflowchart->common) . '</div>';
        if($currentStatus !== '')
        {
            $currentLabel = isset($statusList[$currentStatus]) ? $statusList[$currentStatus] : $currentStatus;
            $html .= '<div class="workflowflowchart-current">' . $escape($this->lang->workflowflowchart->currentStatus) . ': ' . $escape($currentLabel) . '</div>';
        }
        $html .= '</div>';
        if($showManageLink && $this->app->user->admin)
        {
            $link = helper::createLink('workflowflowchart', 'browse', "objectType={$objectType}&mode=edit");
            $html .= '<a class="btn ghost workflowflowchart-manage" href="' . $escape($link) . '"><i class="icon icon-flow"></i> ' . $escape($this->lang->workflowflowchart->configure) . '</a>';
        }
        $html .= '</div>';

        $html .= '<div class="workflowflowchart-board" role="list" aria-label="' . $escape($this->lang->workflowflowchart->common) . '">';
        foreach($nodes as $node)
        {
            $class = $node['status'] === $currentStatus ? 'workflowflowchart-node workflow-node current' : 'workflowflowchart-node workflow-node';
            $html .= '<div class="workflowflowchart-column" role="listitem"><div class="' . $class . '">' . $escape($node['label']) . '</div>';
            $sourceEdges = isset($edgesBySource[$node['id']]) ? $edgesBySource[$node['id']] : array();
            if(!$sourceEdges) $html .= '<div class="workflowflowchart-empty">' . $escape($this->lang->workflowflowchart->flowEmpty) . '</div>';
            foreach($sourceEdges as $edge)
            {
                $target = isset($statusList[$edge['target']]) ? $statusList[$edge['target']] : $edge['target'];
                $label  = !empty($edge['label']) ? $edge['label'] : (isset($actionList[$edge['action']]) ? $actionList[$edge['action']] : $edge['action']);
                $route  = (string)$edge['source'] . '>' . (string)$edge['target'] . ':' . (string)$edge['action'];
                $html  .= '<div class="workflowflowchart-row" data-route="' . $escape($route) . '"><span class="workflowflowchart-action">' . $escape($label) . '</span><span>→</span><span class="workflowflowchart-target">' . $escape($target) . '</span></div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="workflowflowchart-list" aria-label="' . $escape($this->lang->workflowflowchart->transitionList) . '">';
        if(!$enabledEdges)
        {
            $html .= '<div class="workflowflowchart-empty">' . $escape($this->lang->workflowflowchart->flowEmpty) . '</div>';
        }
        foreach($enabledEdges as $edge)
        {
            $source = isset($statusList[$edge['source']]) ? $statusList[$edge['source']] : $edge['source'];
            $target = isset($statusList[$edge['target']]) ? $statusList[$edge['target']] : $edge['target'];
            $label  = !empty($edge['label']) ? $edge['label'] : (isset($actionList[$edge['action']]) ? $actionList[$edge['action']] : $edge['action']);
            $route = (string)$edge['source'] . '>' . (string)$edge['target'] . ':' . (string)$edge['action'];
            $html .= '<div class="workflowflowchart-item" data-route="' . $escape($route) . '"><span>' . $escape($source) . ' → ' . $escape($target) . '</span><span class="workflowflowchart-action">' . $escape($label) . '</span></div>';
        }
        $html .= '</div></section>';

        return $html;
    }

    public function getErrorMessage(string $code, string $source = '', string $target = ''): string
    {
        $this->app->loadLang('workflowflowchart');
        $message = isset($this->lang->workflowflowchart->error->$code) ? $this->lang->workflowflowchart->error->$code : $code;
        if($code == 'transitionDenied') return sprintf($message, $source, $target);
        return $message;
    }
}

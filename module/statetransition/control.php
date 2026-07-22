<?php
declare(strict_types=1);
/**
 * The control file of statetransition module.
 *
 * Admin UI:
 *  - browse: list all 5 objectTypes, show their global/product definition state
 *  - manage: edit a single objectType's statuses + transitions (save via saveDefinition)
 *  - reset:  reset a definition to the default for its objectType
 *  - disable:toggle enabled flag
 *
 * @package statetransition
 */
class statetransition extends control
{
    /**
     * Constructor: ensure mermaid JS is loaded for flow diagrams.
     */
    public function __construct(string $appName = '')
    {
        parent::__construct($appName);
        $this->loadModel('statetransition');
    }

    /**
     * Browse page: show all 5 objectTypes and their global definition state.
     *
     * @param  string $objectType  current active tab
     * @param  int    $productID   product scope (0 for global)
     * @access public
     * @return void
     */
    public function browse(string $objectType = 'story', int $productID = 0)
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes ?? array('story'), true)) $objectType = 'story';

        /* Load product list for scope picker. */
        $products = $this->loadModel('product')->getPairs();
        $products = array(0 => $this->lang->statetransition->globalScope) + $products;

        /* Get effective definition for the selected objectType+productID. */
        $effective = $this->statetransition->getDefinition($objectType, $productID);
        $isDefault = ($effective === null);
        $definition = $isDefault ? $this->statetransition->getDefaultDefinition($objectType) : $effective['definition'];
        $enabled    = $isDefault ? false : $effective['enabled'];
        $version    = $isDefault ? 0 : $effective['version'];

        /* Load lang + scripts. */
        $this->app->loadLang('statetransition');
        $this->view->objectType  = $objectType;
        $this->view->productID   = $productID;
        $this->view->products    = $products;
        $this->view->objectTypes = $this->config->statetransition->objectTypes;
        $this->view->definition  = $definition;
        $this->view->enabled     = $enabled;
        $this->view->isDefault   = $isDefault;
        $this->view->version     = $version;
        $this->view->mermaidText = $this->statetransition->renderMermaid($definition);
        $this->view->title       = $this->lang->statetransition->common;
        $this->display();
    }

    /**
     * Save the definition via AJAX/POST.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return void
     */
    public function manage(string $objectType, int $productID = 0)
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return $this->send(array('result' => 'fail', 'message' => $this->lang->statetransition->errors['objectTypeInvalid']));
        }

        /* Process POST save: only when 'definition' field is actually present in POST body.
           $this->post is truthy even for some GET requests in ZenTao, so check the field. */
        $definitionJSON = $this->post->definition;
        if($definitionJSON)
        {
            $definition = json_decode((string)$definitionJSON, true);
            if(!is_array($definition))
            {
                return $this->send(array('result' => 'fail', 'message' => $this->lang->statetransition->errors['invalidDefinition']));
            }
            $enabled = (bool)($this->post->enabled ?? true);
            $expectedVersion = (int)($this->post->version ?? 0);

            $result = $this->statetransition->saveDefinition($objectType, $productID, $definition, $expectedVersion, $enabled);
            if(!$result['ok'])
            {
                $errorKey = $result['error'];
                $message  = $this->lang->statetransition->errors[$errorKey] ?? $errorKey;
                return $this->send(array('result' => 'fail', 'message' => $message, 'version' => $result['version']));
            }
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'id' => $result['id'], 'version' => $result['version']));
        }

        /* Render the management UI. */
        $effective = $this->statetransition->getDefinition($objectType, $productID);
        $isDefault = ($effective === null);
        $definition = $isDefault ? $this->statetransition->getDefaultDefinition($objectType) : $effective['definition'];
        $enabled    = $isDefault ? true : $effective['enabled'];
        $version    = $isDefault ? 0 : $effective['version'];

        /* Build dropdowns for the visual editor. */
        $roleList = array('' => '') + (array)($this->lang->user->roleList ?? array());
        $users    = $this->loadModel('user')->getPairs('realname|noclosed');

        $this->app->loadLang('statetransition');
        $this->view->objectType   = $objectType;
        $this->view->productID    = $productID;
        $this->view->definition   = $definition;
        $this->view->enabled      = $enabled;
        $this->view->isDefault    = $isDefault;
        $this->view->version      = $version;
        $this->view->statusList   = $this->statetransition->getDefinitionStatusOptions($objectType, $productID);
        $this->view->defaultDef   = $this->statetransition->getDefaultDefinition($objectType);
        $this->view->roleList     = $roleList;
        $this->view->users        = $users;
        $this->view->colorPresets = $this->config->statetransition->colorPresets;
        $this->view->actions      = $this->config->statetransition->actions[$objectType] ?? array();
        $this->view->systemStatuses = $this->config->statetransition->systemStatuses[$objectType] ?? array();
        $this->view->title        = $this->lang->statetransition->manageTitle;
        $this->display();
    }

    /**
     * Reset a definition to default (delete the custom one).
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return void
     */
    public function reset(string $objectType, int $productID = 0)
    {
        /* Trigger only on explicit POST. ZenTao's $this->post is truthy for some GETs. */
        if($_POST)
        {
            $result = $this->statetransition->resetToDefault($objectType, $productID);
            if(!$result['ok'])
            {
                return $this->send(array('result' => 'fail', 'message' => $result['error']));
            }
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess));
        }
        /* GET fallback — perform reset for testing/curl convenience. */
        $result = $this->statetransition->resetToDefault($objectType, $productID);
        return $this->send(array('result' => $result['ok'] ? 'success' : 'fail', 'message' => $result['ok'] ? $this->lang->saveSuccess : $result['error']));
    }

    /**
     * Toggle enabled flag.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return void
     */
    public function toggle(string $objectType, int $productID = 0)
    {
        $effective = $this->statetransition->getDefinition($objectType, $productID);
        if($effective === null) return $this->send(array('result' => 'fail', 'message' => 'definition not found'));

        $newEnabled = !$effective['enabled'];
        $result = $this->statetransition->saveDefinition($objectType, $productID, $effective['definition'], $effective['version'], $newEnabled);
        if(!$result['ok']) return $this->send(array('result' => 'fail', 'message' => $result['error']));
        return $this->send(array('result' => 'success', 'enabled' => $newEnabled));
    }

    /**
     * Trigger a custom button action on a story/bug/task.
     *
     * Used by detail-page custom buttons (PRD §6.4). Looks up the transition, validates
     * via transition(), updates the object's status, and records an action.
     *
     * @param  string $objectType
     * @param  int    $objectID
     * @param  string $transitionKey
     * @access public
     * @return void
     */
    public function triggerCustom(string $objectType, int $objectID, string $transitionKey)
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return $this->send(array('result' => 'fail', 'message' => $this->lang->statetransition->errors['objectTypeInvalid']));
        }

        /* Load the object via the business module. */
        $moduleName = $this->config->statetransition->objectModules[$objectType] ?? $objectType;
        $object = $this->loadModel($moduleName)->getById($objectID);
        if(empty($object)) return $this->send(array('result' => 'fail', 'message' => "object not found"));

        $productID = isset($object->product) ? (int)$object->product : 0;
        $fromStatus = $object->status;

        /* Find the transition by key (any transition, not only isCustom). */
        $row = $this->statetransition->getEffectiveDefinition($objectType, $productID);
        if($row === null) return $this->send(array('result' => 'fail', 'message' => $this->lang->statetransition->errors['definitionNotFound']));

        $transition = null;
        foreach($row['definition']['transitions'] as $tr)
        {
            if($tr['key'] === $transitionKey)
            {
                $transition = $tr;
                break;
            }
        }
        if($transition === null) return $this->send(array('result' => 'fail', 'message' => $this->lang->statetransition->errors['transitionNotFound']));

        /* GET: render comment form when requireComment=true. */
        if(empty($_POST))
        {
            $langCode = $this->app->getClientLang();
            $rawLabel = $transition['buttonLabel'][$langCode] ?? ($transition['label'][$langCode] ?? $transition['action']);
            /* Repair historical \uXXXX escape-sequence corruption. */
            $label = $this->statetransition->repairLabelEscape((string)$rawLabel);
            $this->view->title      = $label;
            $this->view->label      = $label;
            $this->view->objectType = $objectType;
            $this->view->objectID   = $objectID;
            $this->view->transitionKey = $transitionKey;
            $this->view->requireComment = !empty($transition['requireComment']);
            $this->view->toStatus       = $transition['toStatus'];
            /* Provide user list so the modal can render an assignee picker — users have
               asked to be able to set 负责人 during a transition, not just after. */
            $this->view->users      = $this->loadModel('user')->getPairs('noclosed|nodeleted');
            $this->view->assignedTo = $object->assignedTo ?? '';
            $this->display();
            return null;
        }

        $comment = (string)($this->post->comment ?? '');

        /* Run the workflow transition. */
        $decision = $this->statetransition->transition(
            $objectType, $productID, $objectID, $fromStatus,
            $transition['action'],
            $transition['branch'] ?? null,
            $comment
        );
        if(!$decision->ok) return $this->send(array('result' => 'fail', 'message' => $decision->errorMessage));

        /* Update the object's status and status-specific fields.
           getFieldsForStatus() returns the field mapping that mirrors native close/activate/resolve
           methods, so custom transitions produce the same database state. */
        $tableName  = $moduleName === 'story' ? TABLE_STORY : ($moduleName === 'bug' ? TABLE_BUG : TABLE_TASK);
        $assignedTo = (string)($this->post->assignedTo ?? '');
        $newObject  = clone $object;
        $newObject->status = $decision->toStatus;
        $now = helper::now();
        $account = $this->app->user->account;

        /* Get status-specific fields (closed→assignedTo=closed, active→activatedDate, etc.). */
        $statusFields = $this->statetransition->getFieldsForStatus($objectType, $decision->toStatus);

        /* Resolve special markers to actual values. */
        $resolved = array();
        foreach($statusFields as $field => $value)
        {
            if($value === 'now')       $resolved[$field] = $now;
            elseif($value === 'user')  $resolved[$field] = $account;
            else                       $resolved[$field] = $value;
        }

        /* If user explicitly picked an assignee and the target is NOT 'closed' (which
           hardcodes assignedTo='closed'), honour the user's choice. */
        if($decision->toStatus !== 'closed' && $assignedTo !== '' && $assignedTo !== ($object->assignedTo ?? ''))
        {
            $resolved['assignedTo'] = $assignedTo;
        }

        /* Always set target status and lastEditedBy/Date. */
        $resolved['status']       = $decision->toStatus;
        $resolved['lastEditedBy']   = $account;
        $resolved['lastEditedDate'] = $now;

        /* Build and execute the UPDATE. */
        $dao = $this->dao->update($tableName);
        foreach($resolved as $field => $value)
        {
            if($value === null) $dao->set($field)->eq(null);
            else                $dao->set($field)->eq($value);
        }
        $dao->where('id')->eq($objectID)->exec();

        /* Track changes for audit log. */
        foreach($resolved as $field => $value)
        {
            if(is_object($newObject) && property_exists($newObject, $field)) $newObject->$field = $value;
        }

        /* Record an action with history so assignee/status changes appear in the audit log
           consistent with native transitions like activate/close. */
        $actionLabel = $transition['buttonLabel'][$this->app->getClientLang()] ?? $transition['action'];
        $actionID    = $this->loadModel('action')->create($objectType, $objectID, 'customTransition', $comment, $actionLabel);
        if($actionID)
        {
            $changes = common::createChanges($object, $newObject);
            if(!empty($changes)) $this->action->logHistory($actionID, $changes);
        }

        return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'load' => true, 'closeModal' => true));
    }
}

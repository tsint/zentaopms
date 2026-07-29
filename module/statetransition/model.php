<?php
declare(strict_types=1);
/**
 * The model file of statetransition module.
 *
 * Implements:
 *  - Admin API: getDefaultDefinition, validateDefinition, normalizeDefinition, getDefinition, saveDefinition,
 *               copyDefinition, syncFromGlobal, resetToDefault
 *  - Runtime API: transition, assertStatusChange, isActionAllowed, assertEntryState
 *  - UI/helper API: getStatusList, getEntryStatusOptions, getDefinitionStatusOptions, getDefaultToStatus,
 *                   getCustomButtons, renderFlowHtml, renderMermaid
 *
 * Design notes (PRD §5):
 *  - transition() returns the target status from configuration; business code MUST NOT decide target.
 *  - wasUnrestricted=true signals no-constraint (definition missing or disabled) for graceful gray rollout.
 *  - Product override is FULL override — no merge with global.
 *
 * @package statetransition
 */

require_once __DIR__ . '/lib/transitiondecision.class.php';

class statetransitionModel extends model
{
    /**
     * Cache of definitions: keyed by "{scope}:{productID}:{objectType}".
     *
     * @var array
     */
    private static array $defCache = array();

    /**
     * Get the default workflow definition for an objectType.
     *
     * Returns a fresh deep copy so callers can mutate freely.
     *
     * @param  string $objectType
     * @access public
     * @return array
     */
    public function getDefaultDefinition(string $objectType): array
    {
        $defaults = $this->config->statetransition->defaultDefinitions;
        if($objectType === 'epic' && !isset($defaults['epic']))         $defaults['epic'] = $defaults['story'] ?? array();
        if($objectType === 'requirement' && !isset($defaults['requirement'])) $defaults['requirement'] = $defaults['story'] ?? array();

        if(!isset($defaults[$objectType])) return array('schemaVersion' => 1, 'statuses' => array(), 'transitions' => array(), 'entries' => array());

        /* Deep copy via JSON round-trip (closures not present, safe). */
        $json = json_encode($defaults[$objectType]);
        return json_decode($json, true);
    }

    /**
     * Check whether a definition is equal to the object type's default definition.
     *
     * @param  string $objectType
     * @param  array  $definition
     * @access public
     * @return bool
     */
    public function isDefaultDefinition(string $objectType, array $definition): bool
    {
        $current = $this->normalizeDefinition($definition, $objectType);
        $default = $this->normalizeDefinition($this->getDefaultDefinition($objectType), $objectType);
        if(!$current['ok'] || !$default['ok']) return false;

        return $this->canonicalizeDefinition($current['definition']) === $this->canonicalizeDefinition($default['definition']);
    }

    /**
     * Validate a raw definition array.
     *
     * @param  array  $definition
     * @param  string $objectType
     * @access public
     * @return array  ['ok' => bool, 'errors' => [...], 'definition' => ?array]
     */
    public function validateDefinition(array $definition, string $objectType): array
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return array('ok' => false, 'errors' => array(array('key' => 'objectTypeInvalid', 'message' => $this->lang->statetransition->errors['objectTypeInvalid'])), 'definition' => null);
        }
        $definition['_objectType'] = $objectType;
        return $this->validateDefinitionImpl($definition);
    }

    /**
     * Normalize then validate. Returns the canonical definition on success.
     *
     * @param  array  $definition
     * @param  string $objectType
     * @access public
     * @return array  ['ok' => bool, 'errors' => [...], 'definition' => ?array]
     */
    public function normalizeDefinition(array $definition, string $objectType): array
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return array('ok' => false, 'errors' => array(array('key' => 'objectTypeInvalid', 'message' => $this->lang->statetransition->errors['objectTypeInvalid'])), 'definition' => null);
        }
        $normalized = $this->normalizeDefinitionImpl($definition, $objectType);
        return $this->validateDefinition($normalized, $objectType);
    }

    /**
     * Get the effective definition row (already decoded array) for an objectType+productID.
     *
     * Product override completely replaces global (PRD §4.5).
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array|null  array with keys: id, scope, productID, objectType, name, enabled, version, definition (decoded array); null if none.
     */
    public function getDefinition(string $objectType, int $productID): ?array
    {
        /* Product first. */
        $productRow = $this->fetchDefinitionRow('product', $productID, $objectType);
        if($productRow !== null) return $productRow;

        /* Then global. */
        return $this->fetchDefinitionRow('global', 0, $objectType);
    }

    /**
     * Get the active definition for callers that still use the historical effective API.
     *
     * Runtime lifecycle injection has been removed: deleted workflow actions must stay deleted.
     * This method therefore currently returns the same definition as getDefinition().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @return array|null
     * @access public
     */
    public function getEffectiveDefinition(string $objectType, int $productID): ?array
    {
        $row = $this->getDefinition($objectType, $productID);
        if($row === null) return null;

        $row['definition']['transitions'] = $this->injectDefaultTransitions($row['definition'], $objectType);
        return $row;
    }

    /**
     * Fetch a single definition row from DB (with cache).
     *
     * @param  string $scope
     * @param  int    $productID
     * @param  string $objectType
     * @access private
     * @return array|null
     */
    private function fetchDefinitionRow(string $scope, int $productID, string $objectType): ?array
    {
        $cacheKey = "{$scope}:{$productID}:{$objectType}";
        if(array_key_exists($cacheKey, self::$defCache)) return self::$defCache[$cacheKey];

        $row = $this->dao->select('*')->from(TABLE_WORKFLOW_DEFINITION)
            ->where('scope')->eq($scope)
            ->andWhere('productID')->eq($productID)
            ->andWhere('objectType')->eq($objectType)
            ->fetch();

        if(empty($row))
        {
            self::$defCache[$cacheKey] = null;
            return null;
        }

        $decoded = json_decode($row->definition ?? 'null', true);
        if(!is_array($decoded)) $decoded = $this->getDefaultDefinition($objectType);

        /* Repair historically-corrupted label strings (literal "\uXXXX" escape sequences
           that were stored as text instead of being decoded as Chinese chars). */
        $decoded = $this->repairDefinitionLabels($decoded);

        /* Auto-upgrade legacy schema on read. */
        $result = $this->normalizeDefinition($decoded, $objectType);
        if($result['ok']) $decoded = $result['definition'];

        $out = array(
            'id'          => (int)$row->id,
            'scope'       => $row->scope,
            'productID'   => (int)$row->productID,
            'objectType'  => $row->objectType,
            'name'        => $row->name,
            'enabled'     => $row->enabled === '1',
            'version'     => (int)$row->version,
            'definition'  => $decoded,
            'createdBy'   => $row->createdBy,
            'createdDate' => $row->createdDate,
            'editedBy'    => $row->editedBy,
            'editedDate'  => $row->editedDate,
        );
        self::$defCache[$cacheKey] = $out;
        return $out;
    }

    /**
     * Save a definition (insert or update).
     *
     * @param  string $objectType
     * @param  int    $productID    0 for global, productID for product override
     * @param  array  $definition   the new definition (statuses/transitions/entries)
     * @param  int    $expectedVersion  optimistic lock; 0 to skip check on insert
     * @param  bool   $enabled      whether the workflow is active
     * @access public
     * @return array  ['ok' => bool, 'error' => ?string, 'id' => ?int, 'version' => ?int]
     */
    public function saveDefinition(string $objectType, int $productID, array $definition, int $expectedVersion = 0, bool $enabled = true): array
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return array('ok' => false, 'error' => 'objectTypeInvalid', 'id' => null, 'version' => null);
        }

        $normalized = $this->normalizeDefinition($definition, $objectType);
        if(!$normalized['ok'])
        {
            return array('ok' => false, 'error' => $normalized['errors'][0]['key'] ?? 'invalidDefinition', 'id' => null, 'version' => null);
        }
        $definition = $normalized['definition'];

        $scope = $productID > 0 ? 'product' : 'global';
        $existing = $this->fetchDefinitionRow($scope, $productID, $objectType);

        if($existing !== null)
        {
            if($expectedVersion !== 0 && $existing['version'] !== $expectedVersion)
            {
                return array('ok' => false, 'error' => 'versionConflict', 'id' => $existing['id'], 'version' => $existing['version']);
            }
            $newVersion = $existing['version'] + 1;
            $this->dao->update(TABLE_WORKFLOW_DEFINITION)
                ->set('definition')->eq(json_encode($definition))
                ->set('enabled')->eq($enabled ? '1' : '0')
                ->set('version')->eq($newVersion)
                ->set('editedBy')->eq($this->app->user->account ?? '')
                ->set('editedDate')->eq(helper::now())
                ->where('id')->eq($existing['id'])
                ->exec();
            $this->invalidateCache($scope, $productID, $objectType);
            return array('ok' => true, 'error' => null, 'id' => $existing['id'], 'version' => $newVersion);
        }

        /* Insert: default enabled=true (PRD §7.5.4). */
        $insertData = array(
            'scope'       => $scope,
            'productID'   => $productID,
            'objectType'  => $objectType,
            'name'        => $scope === 'global' ? '默认 ' . $objectType . ' 流程' : '产品 ' . $productID . ' ' . $objectType . ' 流程',
            'enabled'     => $enabled ? '1' : '0',
            'version'     => 1,
            'definition'  => json_encode($definition),
            'createdBy'   => $this->app->user->account ?? '',
            'createdDate' => helper::now(),
        );
        $this->dao->insert(TABLE_WORKFLOW_DEFINITION)->data($insertData)->exec();
        $id = $this->dao->lastInsertID();
        $this->invalidateCache($scope, $productID, $objectType);
        return array('ok' => true, 'error' => null, 'id' => (int)$id, 'version' => 1);
    }

    /**
     * Copy a definition from one (objectType, productID) to another.
     *
     * @param  string $fromObjectType
     * @param  int    $fromProductID
     * @param  string $toObjectType
     * @param  int    $toProductID
     * @access public
     * @return array
     */
    public function copyDefinition(string $fromObjectType, int $fromProductID, string $toObjectType, int $toProductID): array
    {
        $source = $this->getDefinition($fromObjectType, $fromProductID);
        if($source === null) return array('ok' => false, 'error' => 'definitionNotFound', 'id' => null, 'version' => null);
        return $this->saveDefinition($toObjectType, $toProductID, $source['definition'], 0, true);
    }

    /**
     * Sync a product-scope definition from the global definition of the same objectType.
     *
     * If global has not been customized yet, use the built-in default definition, matching
     * the effective global workflow shown by the admin UI.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array
     */
    public function syncFromGlobal(string $objectType, int $productID): array
    {
        if($productID <= 0) return array('ok' => false, 'error' => 'productRequired', 'id' => null, 'version' => null);

        $global = $this->fetchDefinitionRow('global', 0, $objectType);
        if($global !== null) return $this->saveDefinition($objectType, $productID, $global['definition'], 0, $global['enabled']);

        return $this->saveDefinition($objectType, $productID, $this->getDefaultDefinition($objectType), 0, true);
    }

    /**
     * Reset a definition to the default for its objectType.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array
     */
    public function resetToDefault(string $objectType, int $productID): array
    {
        $default = $this->getDefaultDefinition($objectType);
        return $this->saveDefinition($objectType, $productID, $default, 0, true);
    }

    /**
     * Invalidate the cache entry for a (scope, productID, objectType).
     *
     * @param  string $scope
     * @param  int    $productID
     * @param  string $objectType
     * @access private
     * @return void
     */
    private function invalidateCache(string $scope, int $productID, string $objectType): void
    {
        $cacheKey = "{$scope}:{$productID}:{$objectType}";
        unset(self::$defCache[$cacheKey]);
    }

    /**
     * Clear all cached definitions. Useful for tests that bypass the model to mutate the table.
     *
     * @access public
     * @return void
     */
    public function clearCache(): void
    {
        self::$defCache = array();
    }

    /**
     * Canonicalize a normalized definition for equality checks.
     *
     * @param  mixed $value
     * @access private
     * @return mixed
     */
    private function canonicalizeDefinition(mixed $value): mixed
    {
        if(is_object($value)) $value = (array)$value;
        if(!is_array($value)) return $value;

        foreach($value as $key => $child) $value[$key] = $this->canonicalizeDefinition($child);
        if(array_keys($value) !== range(0, count($value) - 1)) ksort($value);
        return $value;
    }

    /**
     * Resolve and validate a transition. The primary runtime entry point.
     *
     * Returns transitionDecision. On ok=true, $decision->toStatus is the configured target (never
     * influenced by business code). On ok=false, $decision->errorKey classifies the failure.
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  int     $objectID    only for error/log context
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @param  string  $comment
     * @param  ?object $actor
     * @access public
     * @return transitionDecision
     */
    public function transition(        string  $objectType,
        int     $productID,
        int     $objectID,
        string  $fromStatus,
        string  $action,
        ?string $branch = null,
        string  $comment = '',
        ?object $actor = null
    ): transitionDecision {
        /* Validate objectType. */
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return transitionDecision::fail('objectTypeInvalid', $this->lang->statetransition->errors['objectTypeInvalid']);
        }

        $row = $this->getDefinition($objectType, $productID);
        if($row === null)
        {
            /* No definition → unrestricted. Caller must fall back to business default. */
            return $this->unrestricted($objectType, $fromStatus, $action, $branch);
        }
        if(!$row['enabled'])
        {
            return $this->unrestricted($objectType, $fromStatus, $action, $branch);
        }

        $definition = $row['definition'];
        $matches = $this->findTransitions($definition, $fromStatus, $action, $branch);

        if(empty($matches))
        {
            return transitionDecision::fail('transitionNotFound', $this->lang->statetransition->errors['transitionNotFound']);
        }
        if(count($matches) > 1 && $branch === null)
        {
            /* Ambiguous: caller needs to specify branch. */
            return transitionDecision::fail('ambiguousBranch', $this->lang->statetransition->errors['ambiguousBranch']);
        }

        /* Single match (or caller specified branch). */
        $tr = $matches[0];

        if(!$this->checkActor($tr, $actor))
        {
            return transitionDecision::fail('actorDenied', $this->lang->statetransition->errors['actorDenied']);
        }
        if(!$this->checkComment($tr, $comment))
        {
            return transitionDecision::fail('commentRequired', $this->lang->statetransition->errors['commentRequired']);
        }

        return transitionDecision::ok($tr['toStatus'], $tr, false);
    }

    /**
     * Build a wasUnrestricted=true decision (definition missing or disabled).
     *
     * For gray rollout: target status = fromStatus if business caller didn't supply one via branch;
     * we leave toStatus as '' so business code falls back to its native default.
     *
     * @param  string  $objectType
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @access private
     * @return transitionDecision
     */
    private function unrestricted(string $objectType, string $fromStatus, string $action, ?string $branch): transitionDecision
    {
        $decision = transitionDecision::ok('', null, true);
        return $decision;
    }

    /**
     * Convenience wrapper for business modules: returns the target status string on success,
     * or null on failure (with dao::$errors[] populated for UI feedback).
     *
     * When the workflow has no enabled definition for this scope, returns $fallbackStatus
     * (the business module's native default) — this is the gray-rollout path.
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  int     $objectID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @param  string  $comment
     * @param  string  $fallbackStatus  business default target status, used when unrestricted
     * @access public
     * @return string|null  target status, or null on failure
     */
    public function applyWorkflowTransition(
        string  $objectType,
        int     $productID,
        int     $objectID,
        ?string $fromStatus,
        string  $action,
        ?string $branch,
        string  $comment,
        string  $fallbackStatus = ''
    ): ?string {
        /* If fromStatus is empty (object not loaded / not yet persistent), skip workflow and use fallback. */
        if($fromStatus === '' || $fromStatus === null) return $fallbackStatus;

        $decision = $this->transition($objectType, $productID, $objectID, $fromStatus, $action, $branch, $comment);
        if(!$decision->ok)
        {
            dao::$errors['statetransition'] = $decision->errorMessage;
            return null;
        }
        if($decision->wasUnrestricted) return $fallbackStatus;
        return $decision->toStatus;
    }

    /**
     * Free-form status change (no action). Used by update / batch edit / API direct status writes.
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  int     $objectID
     * @param  string  $fromStatus
     * @param  string  $toStatus
     * @param  string  $comment
     * @param  ?object $actor
     * @access public
     * @return transitionDecision
     */
    public function assertStatusChange(
        string  $objectType,
        int     $productID,
        int     $objectID,
        string  $fromStatus,
        string  $toStatus,
        string  $comment = '',
        ?object $actor = null
    ): transitionDecision {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true))
        {
            return transitionDecision::fail('objectTypeInvalid', $this->lang->statetransition->errors['objectTypeInvalid']);
        }

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled'])
        {
            return $this->unrestricted($objectType, $fromStatus, '', null);
        }

        $definition = $row['definition'];
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] === $fromStatus && $tr['toStatus'] === $toStatus)
            {
                if(!$this->checkActor($tr, $actor))   return transitionDecision::fail('actorDenied', $this->lang->statetransition->errors['actorDenied']);
                if(!$this->checkComment($tr, $comment)) return transitionDecision::fail('commentRequired', $this->lang->statetransition->errors['commentRequired']);
                return transitionDecision::ok($toStatus, $tr, false);
            }
        }
        return transitionDecision::fail('transitionNotFound', $this->lang->statetransition->errors['transitionNotFound']);
    }

    /**
     * Check if an action is allowed for an actor from a status (UI button visibility).
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?object $actor
     * @access public
     * @return bool
     */
    public function isActionAllowed(string $objectType, int $productID, string $fromStatus, string $action, ?object $actor = null): bool
    {
        if($this->isAlwaysPreservedDetailAction($action)) return true;

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled']) return true; /* Unrestricted → allow all. */

        $definition = $row['definition'];
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;
            if($tr['action'] !== $action) continue;
            if($this->checkActor($tr, $actor)) return true;
        }
        return false;
    }

    /**
     * Static wrapper for isClickable (no $this context).
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?object $actor
     * @access public
     * @return bool
     */
    public static function isActionAllowedStatic(string $objectType, int $productID, string $fromStatus, string $action, ?object $actor = null): bool
    {
        global $tester;
        if(isset($tester) && is_object($tester))
        {
            $model = $tester->loadModel('statetransition');
            return $model->isActionAllowed($objectType, $productID, $fromStatus, $action, $actor);
        }
        /* In production, the static call needs an app instance. Fall back to allowed=true if we cannot resolve. */
        return true;
    }

    /**
     * Validate / fix the entry status of a new object.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $status    the requested initial status
     * @access public
     * @return string  the final initial status (possibly fixed up)
     */
    public function assertEntryState(string $objectType, int $productID, string $status): string
    {
        if(!in_array($objectType, $this->config->statetransition->objectTypes, true)) return $status;

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled']) return $status;

        $definition = $row['definition'];
        $entries = $definition['entries'] ?? array();
        if(empty($entries)) return $status;
        if(in_array($status, $entries, true)) return $status;

        /* Fallback: first entry. */
        return $entries[0];
    }

    /**
     * Get status list as [key => label] for the current lang.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array
     */
    public function getStatusList(string $objectType, int $productID): array
    {
        $row = $this->getDefinition($objectType, $productID);
        if($row === null)
        {
            /* Fall back to system lang statusList. */
            return $this->getSystemStatusList($objectType);
        }

        $definition = $row['definition'];
        $lang = $this->getLangCode();
        $out = array();
        foreach($definition['statuses'] ?? array() as $status)
        {
            $out[$status['key']] = $this->pickLabel($status['label'] ?? array(), $lang, $status['key']);
        }
        return $out;
    }

    /**
     * Merge workflow statuses into the module's lang statusList and return the combined list.
     *
     * Custom workflow statuses (e.g. a bug entry status "pending") are absent from the system
     * lang statusList, so dtable statusMap and processStatus() render the raw key instead of the
     * label. This patches the module lang in-place — workflow statuses take precedence, the
     * system list fills any gaps — so every consumer resolves the configured label. It is a
     * no-op (returns the system list unchanged) when no workflow is enabled for the product.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array  merged [key => label]
     */
    public function mergeStatusList(string $objectType, int $productID): array
    {
        $module = $this->config->statetransition->objectModules[$objectType] ?? $objectType;
        $this->app->loadLang($module);
        $langObj = $this->app->lang->{$module};
        $system  = isset($langObj->statusList) ? (array)$langObj->statusList : array();

        /* Workflow statuses take precedence; product flows use global labels as a display fallback only. */
        $workflow = $this->getStatusList($objectType, $productID);
        if($productID > 0) $workflow += $this->getStatusList($objectType, 0);
        $merged = $workflow + $system;
        $langObj->statusList = $merged;
        if($module !== $objectType)
        {
            $this->app->loadLang($objectType);
            if(isset($this->app->lang->{$objectType})) $this->app->lang->{$objectType}->statusList = $merged;
        }
        return $merged;
    }

    /**
     * Get the system status list (lang file) for an objectType.
     *
     * @param  string $objectType
     * @access public
     * @return array
     */
    public function getSystemStatusList(string $objectType): array
    {
        /* epic/requirement/story share the story lang. */
        $module = $this->config->statetransition->objectModules[$objectType] ?? $objectType;
        $this->app->loadLang($module);
        $lang = $this->app->lang->{$module};
        return isset($lang->statusList) ? (array)$lang->statusList : array();
    }

    /**
     * Get entry status options (for create form).
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array  ['key' => ['key' => str, 'text' => str, 'color' => str], ...]
     */
    public function getEntryStatusOptions(string $objectType, int $productID): array
    {
        $row = $this->getDefinition($objectType, $productID);
        $definition = $row === null ? $this->getDefaultDefinition($objectType) : $row['definition'];

        $lang = $this->getLangCode();
        $out = array();
        foreach($definition['entries'] ?? array() as $entryKey)
        {
            foreach($definition['statuses'] ?? array() as $status)
            {
                if($status['key'] === $entryKey)
                {
                    $out[] = array(
                        'key'   => $status['key'],
                        'text'  => $this->pickLabel($status['label'] ?? array(), $lang, $status['key']),
                        'color' => $status['color'] ?? '#999',
                    );
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Get all status options for the change/edit form.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array
     */
    public function getDefinitionStatusOptions(string $objectType, int $productID): array
    {
        $row = $this->getDefinition($objectType, $productID);
        $definition = $row === null ? $this->getDefaultDefinition($objectType) : $row['definition'];

        $lang = $this->getLangCode();
        $out = array();
        foreach($definition['statuses'] ?? array() as $status)
        {
            $out[] = array(
                'key'   => $status['key'],
                'text'  => $this->pickLabel($status['label'] ?? array(), $lang, $status['key']),
                'color' => $status['color'] ?? '#999',
            );
        }
        return $out;
    }

    /**
     * Get the default toStatus for an action from a status (for form pickers).
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @access public
     * @return string  '' if no definition or no match
     */
    public function getDefaultToStatus(string $objectType, int $productID, string $fromStatus, string $action, ?string $branch = null): string
    {
        $row = $this->getDefinition($objectType, $productID);
        if($row === null) return '';

        $matches = $this->findTransitions($row['definition'], $fromStatus, $action, $branch);
        if(empty($matches)) return '';
        return $matches[0]['toStatus'];
    }

    /**
     * Get custom buttons visible from a given status.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $fromStatus
     * @access public
     * @return array  each item: ['key' => str, 'action' => str, 'branch' => ?str, 'buttonLabel' => str, ...]
     */
    public function getCustomButtons(string $objectType, int $productID, string $fromStatus): array
    {
        $row = $this->getDefinition($objectType, $productID);
        if($row === null) return array();

        $definition = $row['definition'];
        $lang = $this->getLangCode();
        $out = array();
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(empty($tr['isCustom'])) continue;
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;
            $out[] = array(
                'key'            => $tr['key'],
                'action'         => $tr['action'],
                'branch'         => $tr['branch'] ?? null,
                'buttonLabel'    => $this->pickLabel($tr['buttonLabel'] ?? array(), $lang, $tr['action']),
                'buttonIcon'     => $tr['buttonIcon'] ?? null,
                'buttonOrder'    => $tr['buttonOrder'] ?? 0,
                'buttonGroup'    => $tr['buttonGroup'] ?? 'primary',
                'requireComment' => !empty($tr['requireComment']),
                'toStatus'       => $tr['toStatus'],
            );
        }
        usort($out, fn($a, $b) => $a['buttonOrder'] <=> $b['buttonOrder']);
        return $out;
    }

    /**
     * Get workflow buttons to inject into detail page for transitions the user can trigger.
     *
     * Returns button configs for ALL enabled workflow transitions from $fromStatus where
     * the current actor passes checkActor, EXCLUDING actions already covered by the
     * caller's native action list (to avoid duplicate buttons).
     *
     * Each returned item is shaped like buildOperateMenu output:
     *   ['name', 'text', 'hint', 'icon', 'url', 'data-app', 'data-toggle']
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  int    $objectID
     * @param  string $fromStatus
     * @param  array  $existingActionNames  lowercase native action names already in menu (e.g. ['change', 'review'])
     * @access public
     * @return array
     */
    public function getDetailActionButtons(string $objectType, int $productID, int $objectID, string $fromStatus, array $existingActionNames = array()): array
    {
        if(!$this->config->statetransition->globalEnabled) return array();

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled']) return array();

        $definition   = $row['definition'];
        $lang         = $this->getLangCode();
        $existingSet  = array();
        foreach($existingActionNames as $name) $existingSet[strtolower((string)$name)] = true;

        /* Icon mapping for built-in actions. */
        $iconMap = array(
            'submitreview'   => 'confirm',
            'review'         => 'search',
            'change'         => 'change',
            'recallreview'   => 'undo',
            'recallchange'   => 'undo',
            'assignto'       => 'hand-right',
            'confirm'        => 'ok',
            'close'          => 'off',
            'activate'       => 'magic',
            'resolve'        => 'ok',
            'start'          => 'play',
            'restart'        => 'play',
            'pause'          => 'pause',
            'finish'         => 'ok',
            'cancel'         => 'ban',
        );

        $out = array();
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;

            /* Skip actions already covered natively — avoids duplicate buttons. */
            $actionLower = strtolower((string)$tr['action']);
            if(isset($existingSet[$actionLower])) continue;

            /* Actor check — skip transitions the current user can't trigger. */
            if(!$this->checkActor($tr, null)) continue;

            /* Label: prefer buttonLabel, fallback to transition label, then action. */
            $label = $this->pickLabel($tr['buttonLabel'] ?? array(), $lang, '');
            if($label === '') $label = $this->pickLabel($tr['label'] ?? array(), $lang, $tr['action']);
            if($label === '') $label = $tr['action'];

            $url = helper::createLink('statetransition', 'triggerCustom', "objectType={$objectType}&objectID={$objectID}&transitionKey=" . urlencode($tr['key']));

            /* Always open modal: GET renders comment form, POST processes transition.
               This gives the user a chance to enter a comment even when requireComment=false. */
            $out[] = array(
                'name'        => 'workflow_' . $tr['key'],
                'text'        => $label,
                'hint'        => $label,
                'icon'        => $tr['buttonIcon'] ?? ($iconMap[$actionLower] ?? 'magic'),
                'url'         => $url,
                'data-app'    => $this->app->tab ?? '',
                'data-toggle' => 'modal',
                'data-size'   => 'sm',
            );
        }
        return $out;
    }

    /**
     * Filter native detail-page actions by the stored workflow definition.
     *
     * Only workflow-controlled status actions are filtered. Non-status actions
     * such as edit, copy, subdivide, createTask and recordWorkhour are preserved.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $fromStatus
     * @param  array  $actions
     * @access public
     * @return array
     */
    public function filterDetailActions(string $objectType, int $productID, string $fromStatus, array $actions): array
    {
        if(!$this->config->statetransition->globalEnabled) return $actions;

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled']) return $actions;

        $workflowActions = array_flip(array_map('strtolower', array_map('strval', $this->config->statetransition->actions[$objectType] ?? array())));
        if(empty($workflowActions)) return $actions;

        $allowedActions = $this->getAllowedActions($row['definition'], $fromStatus);
        foreach($actions as $key => $action)
        {
            if(!is_array($action))
            {
                unset($actions[$key]);
                continue;
            }
            if(isset($action['type']) && $action['type'] === 'divider') continue;

            $actionName = $this->extractDetailActionName($action);
            if($this->isAlwaysPreservedDetailAction($actionName)) continue;

            $candidateActions = $this->getWorkflowActionAliases($actionName);
            if(empty($candidateActions)) continue;

            $isWorkflowAction = false;
            $isAllowed        = false;
            foreach($candidateActions as $actionName)
            {
                if(!isset($workflowActions[$actionName])) continue;

                $isWorkflowAction = true;
                if(isset($allowedActions[$actionName]))
                {
                    $isAllowed = true;
                    break;
                }
            }

            if($isWorkflowAction && !$isAllowed) unset($actions[$key]);
        }

        return array_values($actions);
    }

    /**
     * Get enabled transition actions from a status, keyed by action.
     *
     * @param  array  $definition
     * @param  string $fromStatus
     * @access private
     * @return array
     */
    private function getAllowedActions(array $definition, string $fromStatus): array
    {
        $allowed = array();
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;
            if(!$this->checkActor($tr, null)) continue;

            $action = strtolower((string)$tr['action']);
            if($action !== '') $allowed[$action] = true;
        }
        return $allowed;
    }

    /**
     * Extract action name from buildOperateMenu item.
     *
     * @param  array $action
     * @access private
     * @return string
     */
    private function extractDetailActionName(array $action): string
    {
        foreach(array('name', 'key', 'id') as $field)
        {
            if(!empty($action[$field])) return strtolower((string)$action[$field]);
        }

        $url = (string)($action['url'] ?? '');
        if($url !== '' && preg_match('/[?&]f=([^&]+)/', $url, $matches)) return strtolower($matches[1]);
        if($url !== '' && preg_match('/\/([a-zA-Z0-9_]+)\.html(?:[?#]|$)/', $url, $matches)) return strtolower($matches[1]);

        return '';
    }

    /**
     * Map native UI action names to workflow action names.
     *
     * @param  string $actionName
     * @access private
     * @return array
     */
    private function getWorkflowActionAliases(string $actionName): array
    {
        $actionName = strtolower($actionName);
        if($actionName === '') return array();

        $aliases = array(
            'submitreview' => array('submitreview'),
            'recall'       => array('recallreview', 'recallchange'),
        );

        return $aliases[$actionName] ?? array($actionName);
    }

    /**
     * Detail actions that must not be removed by workflow status-transition filtering.
     *
     * @param  string $actionName
     * @access private
     * @return bool
     */
    private function isAlwaysPreservedDetailAction(string $actionName): bool
    {
        return in_array(strtolower($actionName), array('assignto'), true);
    }

    /**
     * Convenience wrapper: checkActor using tao's protected method via public bridge.
     *
     * @param  array       $transition
     * @param  object|null $actor
     * @access public
     * @return bool
     */
    public function checkActorBridge(array $transition, ?object $actor): bool
    {
        return $this->checkActor($transition, $actor);
    }

    /**
     * Get custom button-label overrides for native action buttons.
     *
     * Returns a map: [lowercase_action => label_text] for transitions where:
     *  - fromStatus matches current status
     *  - The transition has a non-empty buttonLabel (or label that differs from action)
     *  - There is exactly ONE enabled transition per action (no branch confusion)
     *
     * Callers (story/bug/task view pages) use this to override the native
     * actionList[action]['text'] so the native button reflects the user's
     * custom workflow label instead of the default action name.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $fromStatus
     * @access public
     * @return array  e.g. ['activate' => '测试', 'close' => '快速关闭']
     */
    public function getActionLabelOverrides(string $objectType, int $productID, string $fromStatus): array
    {
        if(!$this->config->statetransition->globalEnabled) return array();

        $row = $this->getDefinition($objectType, $productID);
        if($row === null || !$row['enabled']) return array();

        $definition = $row['definition'];
        $lang       = $this->getLangCode();

        /* Group transitions by action to detect single-vs-branch case. */
        $byAction = array();
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;
            $actionLower = strtolower((string)$tr['action']);
            if(!isset($byAction[$actionLower])) $byAction[$actionLower] = array();
            $byAction[$actionLower][] = $tr;
        }

        $overrides = array();
        $defaultActionLabels = $this->lang->statetransition->actionList ?? array();
        foreach($byAction as $actionLower => $transitions)
        {
            /* Skip when multiple branches exist for same action — native button
               opens a form where user picks branch; overriding text would be misleading. */
            if(count($transitions) > 1) continue;

            $tr = $transitions[0];

            /* Prefer buttonLabel, fall back to label. */
            $label = $this->pickLabel($tr['buttonLabel'] ?? array(), $lang, '');
            if($label === '') $label = $this->pickLabel($tr['label'] ?? array(), $lang, '');

            /* Skip if label is empty. */
            if($label === '') continue;

            /* Skip if label matches the action's default label (no real override). */
            $defaultLabel = $defaultActionLabels[$actionLower] ?? ($defaultActionLabels[$tr['action']] ?? '');
            if($label === $defaultLabel) continue;

            $overrides[$actionLower] = $label;
        }
        return $overrides;
    }

    /**
     * Render the Mermaid state diagram text for a definition.
     *
     * @param  array  $definition
     * @param  string $currentStatus  highlight this state
     * @access public
     * @return string  raw Mermaid source
     */
    public function renderMermaid(array $definition, string $currentStatus = ''): string
    {
        $lines = array('stateDiagram-v2');
        $lang  = $this->getLangCode();

        foreach($definition['statuses'] ?? array() as $status)
        {
            $key = (string)($status['key'] ?? '');
            if($key === '') continue;

            $label = $this->pickLabel($status['label'] ?? array(), $lang, $key);
            $lines[] = '    state "' . $this->escapeMermaidLabel($label) . '" as ' . $key;
        }

        /* Entries → from [*]. */
        foreach($definition['entries'] ?? array() as $entry)
        {
            $lines[] = "    [*] --> {$entry}";
        }

        /* Transitions. */
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if(($tr['fromStatus'] ?? '') === ($tr['toStatus'] ?? '')) continue;
            $label = $this->getTransitionMermaidLabel($tr, $lang);
            $lines[] = "    {$tr['fromStatus']} --> {$tr['toStatus']} : {$label}";
        }

        /* Highlight current. */
        if($currentStatus !== '')
        {
            $lines[] = "    class {$currentStatus} current";
            $lines[] = "    classDef current fill:#fff3cd,stroke:#f39c12,stroke-width:2px";
        }
        return implode("\n", $lines);
    }

    /**
     * Get display text for a Mermaid transition edge.
     *
     * Prefer the configured transition display name, then custom button text, then
     * the localized action name. This keeps the diagram readable while preserving
     * status keys for Mermaid ids and click mapping.
     *
     * @param  array  $transition
     * @param  string $lang
     * @access private
     * @return string
     */
    private function getTransitionMermaidLabel(array $transition, string $lang): string
    {
        $action = (string)($transition['action'] ?? '');
        $label  = $this->pickLabel($transition['label'] ?? array(), $lang, '');
        if($label === '') $label = $this->pickLabel($transition['buttonLabel'] ?? array(), $lang, '');
        if($label !== '') return $this->escapeMermaidLabel($label);

        $actionList = $this->lang->statetransition->actionList ?? array();
        $label = $actionList[$action] ?? $action;
        if(!empty($transition['branch']))
        {
            $branch = (string)$transition['branch'];
            $branchList = $this->lang->statetransition->branchList ?? array();
            $label .= '/' . ($branchList[$branch] ?? $branch);
        }

        return $this->escapeMermaidLabel($label);
    }

    /**
     * Escape text used as a Mermaid label.
     *
     * @param  string $label
     * @access private
     * @return string
     */
    private function escapeMermaidLabel(string $label): string
    {
        return str_replace(array('\\', '"', "\r", "\n"), array('\\\\', "'", ' ', ' '), $label);
    }

    /**
     * Render the flow HTML section for embedding in detail pages.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $currentStatus
     * @access public
     * @return string
     */
    public function renderFlowHtml(string $objectType, int $productID, string $currentStatus): string
    {
        $row = $this->getDefinition($objectType, $productID);
        if($row === null)
        {
            /* No definition → no flow. */
            return '';
        }

        $definition = $row['definition'];
        $mermaid = $this->renderMermaid($definition, $currentStatus);
        $currentLabel = '';
        $lang = $this->getLangCode();
        foreach($definition['statuses'] ?? array() as $status)
        {
            if($status['key'] === $currentStatus)
            {
                $currentLabel = $this->pickLabel($status['label'] ?? array(), $lang, $status['key']);
                break;
            }
        }

        $currentText = htmlspecialchars($currentLabel);
        $html  = "<section class='statetransition-detail'>";
        $html .= "<h4>" . htmlspecialchars($this->lang->statetransition->flowDiagram);
        if($currentStatus !== '') $html .= " <small>" . htmlspecialchars($this->lang->statetransition->currentStatus) . ": {$currentText}</small>";
        $html .= "</h4>";
        $source = htmlspecialchars($mermaid, ENT_QUOTES);
        $html .= "<div class='statetransition-mermaid' data-mermaid-source='{$source}'>" . htmlspecialchars($mermaid) . "</div>";
        /* Admin can configure. */
        if(common::hasPriv('statetransition', 'browse'))
        {
            $url = helper::createLink('statetransition', 'browse', "objectType={$objectType}&productID={$productID}");
            $html .= "<a href='{$url}' class='btn btn-sm'>" . htmlspecialchars($this->lang->statetransition->configureWorkflow) . "</a>";
        }
        $html .= "</section>";
        return $html;
    }

    /**
     * Get the current language code (zh_cn / en / ...).
     *
     * @access private
     * @return string
     */
    private function getLangCode(): string
    {
        $clientLang = $this->app->getClientLang();
        return str_replace('-', '_', $clientLang);
    }

    /**
     * Pick a label from a multi-lang map with fallback.
     *
     * @param  array  $label
     * @param  string $lang
     * @param  string $fallback
     * @access private
     * @return string
     */
    private function pickLabel(array $label, string $lang, string $fallback): string
    {
        if(isset($label[$lang]) && $label[$lang] !== '') return $this->repairLabelEscape($label[$lang]);
        if(isset($label['zh_cn']) && $label['zh_cn'] !== '') return $this->repairLabelEscape($label['zh_cn']);
        if(isset($label['en']) && $label['en'] !== '') return $this->repairLabelEscape($label['en']);
        return $fallback;
    }

    /**
     * Repair historically-corrupted label strings.
     *
     * Some legacy data stored literal "\uXXXX" escape sequences as text (instead of
     * actual UTF-8 characters) due to historical double-encoding. This method detects
     * such sequences and decodes them back to real UTF-8.
     *
     * Pattern: '\uXXXX' (literal 6 chars: backslash-u-4-hexdigits) → actual UTF-8 char.
     *
     * @param  string $label
     * @access public
     * @return string
     */
    public function repairLabelEscape(string $label): string
    {
        if($label === '') return $label;

        /* Match literal \uXXXX sequences (6 chars: '\','u',4 hex digits). */
        if(strpos($label, '\\u') === false) return $label;

        $repaired = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            function($m) {
                $codepoint = hexdec($m[1]);
                /* Only decode valid Unicode codepoints (BMP only, since \uXXXX is BMP-limited). */
                if($codepoint > 0 && $codepoint <= 0xFFFF) {
                    return mb_convert_encoding('&#' . $codepoint . ';', 'UTF-8', 'HTML-ENTITIES');
                }
                return $m[0];
            },
            $label
        );

        return $repaired ?? $label;
    }

    /**
     * Walk a definition array and repair corrupted labels in statuses/transitions.
     *
     * Used during fetchDefinitionRow to repair legacy data on read. Future saves
     * overwrite the repaired data with clean values, gradually fixing the DB.
     *
     * @param  array $definition
     * @access public
     * @return array
     */
    public function repairDefinitionLabels(array $definition): array
    {
        /* Repair status labels. */
        foreach($definition['statuses'] ?? array() as $idx => $status)
        {
            if(empty($status['label']) || !is_array($status['label'])) continue;
            foreach($status['label'] as $lang => $text)
            {
                if(is_string($text)) $definition['statuses'][$idx]['label'][$lang] = $this->repairLabelEscape($text);
            }
        }

        /* Repair transition labels + buttonLabels. */
        foreach($definition['transitions'] ?? array() as $idx => $tr)
        {
            foreach(array('label', 'buttonLabel') as $field)
            {
                if(empty($tr[$field]) || !is_array($tr[$field])) continue;
                foreach($tr[$field] as $lang => $text)
                {
                    if(is_string($text)) $definition['transitions'][$idx][$field][$lang] = $this->repairLabelEscape($text);
                }
            }
        }

        return $definition;
    }

    /**
     * Return status-specific fields that must be set when transitioning to $toStatus.
     *
     * Mirrors the field-setting logic in native close/activate/resolve/finish/cancel methods
     * so that custom transitions produce the same database state.
     *
     * Special values:
     *  - 'now'  → helper::now() at apply time
     *  - 'user' → $currentUser at apply time
     *  - ''     → empty string (clear the field)
     *  - null   → SQL NULL
     *  - other  → literal value
     *
     * @param  string $objectType  bug|story|task
     * @param  string $toStatus    target status
     * @return array  fieldName => value mapping (empty array if no special fields needed)
     * @access public
     */
    public function getFieldsForStatus(string $objectType, string $toStatus): array
    {
        /* Fields common to all object types when closing. */
        $closeBase = array(
            'assignedTo'  => 'closed',
            'closedBy'    => 'user',
            'closedDate'  => 'now',
        );

        $map = array(
            'bug' => array(
                'closed' => array_merge($closeBase, array(
                    'confirmed'    => 1,
                    'assignedDate' => 'now',
                )),
                'active' => array(
                    'activatedDate' => 'now',
                    'assignedDate'  => 'now',
                    'resolution'    => '',
                    'resolvedBy'    => '',
                    'resolvedBuild' => '',
                    'resolvedDate'  => null,
                    'closedBy'      => '',
                    'closedDate'    => null,
                ),
                'resolved' => array(
                    'resolvedBy'    => 'user',
                    'resolvedDate'  => 'now',
                    'confirmed'     => 1,
                    'assignedDate'  => 'now',
                ),
            ),
            'story' => array(
                'closed' => array_merge($closeBase, array(
                    'stage'        => 'closed',
                    'assignedDate' => 'now',
                )),
                'active' => array(
                    'activatedDate'  => 'now',
                    'assignedDate'   => 'now',
                    'closedBy'       => '',
                    'closedReason'   => '',
                    'closedDate'     => null,
                    'retractedBy'    => '',
                    'retractedReason'=> '',
                    'retractedDate'  => null,
                ),
            ),
            'task' => array(
                'closed' => array_merge($closeBase, array(
                    'assignedDate' => 'now',
                )),
                'active' => array(
                    'activatedDate' => 'now',
                    'assignedDate'  => 'now',
                    'finishedBy'    => '',
                    'canceledBy'    => '',
                    'closedBy'      => '',
                    'closedReason'  => '',
                    'finishedDate'  => null,
                    'canceledDate'  => null,
                    'closedDate'    => null,
                ),
                'done' => array(
                    'finishedBy'   => 'user',
                    'finishedDate' => 'now',
                    'left'         => 0,
                    'assignedDate' => 'now',
                ),
                'cancel' => array(
                    'canceledBy'   => 'user',
                    'canceledDate' => 'now',
                    'assignedDate' => 'now',
                    'finishedBy'   => '',
                    'finishedDate' => null,
                ),
            ),
        );

        return $map[$objectType][$toStatus] ?? array();
    }
}

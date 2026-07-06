<?php
declare(strict_types=1);
/**
 * The tao file of statetransition module.
 *
 * Pure helpers: validation, normalization, transition lookup, actor/comment checks.
 * No DB access here — keep these side-effect free for unit testing.
 *
 * @package statetransition
 */
class statetransitionTao extends statetransitionModel
{
    /**
     * Validate a definition array.
     *
     * Returns array('ok' => bool, 'errors' => [['key' => i18nKey, 'message' => str], ...]).
     * On ok=true, the normalized definition is in 'definition'.
     *
     * @param  array  $def  raw decoded definition (already array-cast)
     * @access protected
     * @return array
     */
    protected function validateDefinitionImpl(array $def): array
    {
        $errors = array();
        $config = $this->config->statetransition;

        /* objectType passes via $def['_objectType'] (set by caller) — used for action whitelist & systemStatuses. */
        $objectType   = $def['_objectType'] ?? '';
        $validTypes   = $config->objectTypes;
        $systemKeys   = $config->systemStatuses[$objectType] ?? array();
        $allowedActions = $config->actions[$objectType] ?? array();
        $keyPattern   = $config->statusKeyPattern;

        /* schemaVersion must be present. */
        if(!isset($def['schemaVersion']) || !is_int($def['schemaVersion']))
        {
            $errors[] = array('key' => 'invalidDefinition', 'message' => $this->lang->statetransition->errors['invalidDefinition']);
            return array('ok' => false, 'errors' => $errors, 'definition' => null);
        }

        /* statuses must be a non-empty list. */
        if(empty($def['statuses']) || !is_array($def['statuses']))
        {
            $errors[] = array('key' => 'invalidDefinition', 'message' => 'Invalid statuses list');
            return array('ok' => false, 'errors' => $errors, 'definition' => null);
        }

        /* Check each status. */
        $seenKeys = array();
        $statusKeySet = array();
        foreach($def['statuses'] as $i => $status)
        {
            if(!is_array($status))
            {
                $errors[] = array('key' => 'invalidDefinition', 'message' => "statuses[$i] must be an object");
                continue;
            }
            $key = $status['key'] ?? '';
            if(!is_string($key) || $key === '' || !preg_match($keyPattern, $key))
            {
                $errors[] = array('key' => 'statusKeyInvalid', 'message' => $this->lang->statetransition->errors['statusKeyInvalid'] . ": $key");
                continue;
            }
            if(isset($seenKeys[$key]))
            {
                $errors[] = array('key' => 'statusKeyDuplicate', 'message' => $this->lang->statetransition->errors['statusKeyDuplicate'] . ": $key");
                continue;
            }
            /* Custom status must not collide with system keys for OTHER positions. */
            $isSystem = $status['isSystem'] ?? false;
            if(!$isSystem && in_array($key, $systemKeys))
            {
                $errors[] = array('key' => 'systemStatusLocked', 'message' => $this->lang->statetransition->errors['systemStatusLocked'] . ": $key");
                continue;
            }
            /* If marked isSystem, must actually be in the system list. */
            if($isSystem && !in_array($key, $systemKeys))
            {
                $errors[] = array('key' => 'systemStatusLocked', 'message' => $this->lang->statetransition->errors['systemStatusLocked'] . ": $key");
                continue;
            }

            $seenKeys[$key] = true;
            $statusKeySet[$key] = true;
        }

        /* Check transitions. */
        $seenTransKeys = array();
        $seenTriples  = array(); /* (from, action, branch) → for duplicate detection */
        foreach($def['transitions'] ?? array() as $i => $tr)
        {
            if(!is_array($tr))
            {
                $errors[] = array('key' => 'invalidDefinition', 'message' => "transitions[$i] must be an object");
                continue;
            }
            $fromStatus = $tr['fromStatus'] ?? '';
            $toStatus   = $tr['toStatus']   ?? '';
            $action     = $tr['action']     ?? '';
            $branch     = array_key_exists('branch', $tr) ? $tr['branch'] : null;
            $transKey   = $tr['key'] ?? '';

            if($transKey === '' || isset($seenTransKeys[$transKey]))
            {
                $errors[] = array('key' => 'duplicateTransition', 'message' => $this->lang->statetransition->errors['duplicateTransition'] . ": $transKey");
                continue;
            }
            $seenTransKeys[$transKey] = true;

            if(!isset($statusKeySet[$fromStatus]) || !isset($statusKeySet[$toStatus]))
            {
                $errors[] = array('key' => 'transitionRefInvalid', 'message' => $this->lang->statetransition->errors['transitionRefInvalid'] . ": $transKey");
                continue;
            }

            /* Action must be whitelisted OR custom_*. */
            $isCustomAction = is_string($action) && str_starts_with($action, 'custom_');
            if(!$isCustomAction && !in_array($action, $allowedActions))
            {
                $errors[] = array('key' => 'actionInvalid', 'message' => $this->lang->statetransition->errors['actionInvalid'] . ": $action");
                continue;
            }

            /* Duplicate (fromStatus, action, branch) among enabled transitions is forbidden. */
            $enabled = $tr['enabled'] ?? true;
            if($enabled)
            {
                $tripleKey = $fromStatus . '|' . $action . '|' . ($branch ?? '');
                if(isset($seenTriples[$tripleKey]))
                {
                    $errors[] = array('key' => 'duplicateTransition', 'message' => $this->lang->statetransition->errors['duplicateTransition'] . ": $tripleKey");
                    continue;
                }
                $seenTriples[$tripleKey] = true;
            }

            /* Custom button must have buttonLabel. */
            $isCustomButton = $tr['isCustom'] ?? false;
            if($isCustomButton && empty($tr['buttonLabel']))
            {
                $errors[] = array('key' => 'invalidDefinition', 'message' => "Custom button must have buttonLabel: $transKey");
                continue;
            }
            /* Custom button action must be custom_*. */
            if($isCustomButton && !$isCustomAction)
            {
                $errors[] = array('key' => 'actionInvalid', 'message' => "Custom button action must start with custom_: $transKey");
                continue;
            }
        }

        /* entries must reference existing statuses. */
        $entries = $def['entries'] ?? array();
        if(!is_array($entries)) $entries = array();
        foreach($entries as $entryKey)
        {
            if(!isset($statusKeySet[$entryKey]))
            {
                $errors[] = array('key' => 'transitionRefInvalid', 'message' => $this->lang->statetransition->errors['transitionRefInvalid'] . " (entry): $entryKey");
            }
        }

        if(!empty($errors)) return array('ok' => false, 'errors' => $errors, 'definition' => null);

        /* Strip internal _objectType before returning. */
        unset($def['_objectType']);
        return array('ok' => true, 'errors' => array(), 'definition' => $def);
    }

    /**
     * Normalize a raw decoded definition.
     *
     * - Converts legacy field names (nodes/edges) to statuses/transitions if present.
     * - Fills missing fields with sane defaults.
     * - Bumps schemaVersion to current.
     *
     * @param  array  $def
     * @param  string $objectType
     * @access protected
     * @return array
     */
    protected function normalizeDefinitionImpl(array $def, string $objectType): array
    {
        /* Legacy migration: nodes/edges → statuses/transitions. */
        if(isset($def['nodes']) && !isset($def['statuses'])) $def['statuses'] = $def['nodes'];
        if(isset($def['edges']) && !isset($def['transitions'])) $def['transitions'] = $def['edges'];
        unset($def['nodes'], $def['edges']);

        $def['schemaVersion'] = $this->config->statetransition->schemaVersion;

        /* Ensure required top-level keys. */
        if(!isset($def['statuses']) || !is_array($def['statuses'])) $def['statuses'] = array();
        if(!isset($def['transitions']) || !is_array($def['transitions'])) $def['transitions'] = array();
        if(!isset($def['entries']) || !is_array($def['entries'])) $def['entries'] = array();

        /* Normalize each status. */
        $normalizedStatuses = array();
        $systemKeys = $this->config->statetransition->systemStatuses[$objectType] ?? array();
        $defaultDef = $this->getDefaultDefinition($objectType);
        $defaultStatusesByKey = array();
        foreach($defaultDef['statuses'] as $ds) $defaultStatusesByKey[$ds['key']] = $ds;

        foreach($def['statuses'] as $status)
        {
            if(!is_array($status)) continue;
            $key = $status['key'] ?? '';
            if($key === '') continue;

            $isSystem = in_array($key, $systemKeys);
            $baseLabel = $defaultStatusesByKey[$key]['label'] ?? array('zh_cn' => $key, 'en' => $key);

            /* Empty label falls back to the default (for system statuses) or to the key. */
            $labelInput = !empty($status['label']) ? $status['label'] : $baseLabel;

            $normalizedStatuses[] = array(
                'key'        => $key,
                'label'      => $this->normalizeLabel($labelInput, $key),
                'category'   => $this->normalizeEnum($status['category'] ?? null, array('normal', 'abnormal', 'terminal'), $defaultStatusesByKey[$key]['category'] ?? 'normal'),
                'color'      => is_string($status['color'] ?? null) && $status['color'] !== '' ? $status['color'] : ($defaultStatusesByKey[$key]['color'] ?? '#999999'),
                'isSystem'   => $isSystem,
                'isEntry'    => (bool)($status['isEntry'] ?? false),
                'fieldRules' => $status['fieldRules'] ?? new stdClass(),
            );
        }
        $def['statuses'] = $normalizedStatuses;

        /* entries[] is the canonical source of truth for entry states.
           - Validate each entry references an existing status.
           - If entries[] is empty/missing, fall back to deriving from status.isEntry
             (backwards compat for hand-edited JSON).
           - DO NOT auto-add statuses with isEntry=true on top of explicit entries[]
             (that would override user UI toggles that only update entries[]). */
        $statusKeySet = array();
        foreach($def['statuses'] as $s) $statusKeySet[$s['key']] = true;

        $entrySet = array();
        foreach($def['entries'] as $e)
        {
            if(isset($statusKeySet[$e])) $entrySet[$e] = true;
        }
        if(empty($entrySet))
        {
            foreach($def['statuses'] as $s)
            {
                if(!empty($s['isEntry'])) $entrySet[$s['key']] = true;
            }
        }
        $def['entries'] = array_keys($entrySet);

        /* Also sync status.isEntry FROM entries[] so the two representations stay consistent. */
        foreach($def['statuses'] as &$s)
        {
            $s['isEntry'] = isset($entrySet[$s['key']]);
        }
        unset($s);

        /* Normalize each transition. */
        $normalizedTransitions = array();
        foreach($def['transitions'] as $tr)
        {
            if(!is_array($tr)) continue;
            $fromStatus = $tr['fromStatus'] ?? '';
            $toStatus   = $tr['toStatus']   ?? '';
            $action     = $tr['action']     ?? '';
            if($fromStatus === '' || $toStatus === '' || $action === '') continue;
            if(!isset($statusKeySet[$fromStatus]) || !isset($statusKeySet[$toStatus])) continue;

            $branch = array_key_exists('branch', $tr) ? $tr['branch'] : null;
            /* Generate a key if missing. */
            $key = $tr['key'] ?? '';
            if($key === '')
            {
                $branchSuffix = $branch === null ? '' : '-' . $branch;
                $key = $fromStatus . '-to-' . $toStatus . '-via-' . $action . $branchSuffix;
            }

            $normalizedTransitions[] = array(
                'key'             => $key,
                'fromStatus'      => $fromStatus,
                'toStatus'        => $toStatus,
                'action'          => $action,
                'branch'          => $branch,
                'label'           => $this->normalizeLabel($tr['label'] ?? null, $key),
                'roles'           => $this->normalizeStringArray($tr['roles'] ?? array()),
                'accounts'        => $this->normalizeStringArray($tr['accounts'] ?? array()),
                'requireComment'  => (bool)($tr['requireComment'] ?? false),
                'enabled'         => (bool)($tr['enabled'] ?? true),
                'isCustom'        => (bool)($tr['isCustom'] ?? false),
                'buttonLabel'     => $this->normalizeLabel($tr['buttonLabel'] ?? null, null),
                'buttonIcon'      => is_string($tr['buttonIcon'] ?? null) ? $tr['buttonIcon'] : null,
                'buttonOrder'     => (int)($tr['buttonOrder'] ?? 0),
                'buttonGroup'     => $this->normalizeEnum($tr['buttonGroup'] ?? null, array('primary', 'more', 'danger'), 'primary'),
                'sideEffects'     => $tr['sideEffects'] ?? array(),
                'condition'       => $tr['condition'] ?? null,
            );
        }
        $def['transitions'] = $normalizedTransitions;

        return $def;
    }

    /**
     * Inject default close/activate/resolve transitions for non-terminal statuses.
     *
     * Called at runtime (not during normalization) so the stored definition stays clean
     * and admins can remove unwanted transitions. Auto-injected transitions are appended
     * to the transitions array and marked with isAutoInjected=true.
     *
     * @param  array  $def        normalized definition
     * @param  string $objectType bug|story|task|epic|requirement
     * @return array  updated transitions array
     * @access protected
     */
    protected function injectDefaultTransitions(array $def, string $objectType): array
    {
        $transitions = $def['transitions'];

        /* Determine which lifecycle actions apply to this object type. */
        $lifecycleActions = array();
        if(in_array($objectType, array('story', 'epic', 'requirement'), true))
        {
            $lifecycleActions = array(
                'close'    => array('toStatus' => 'closed',   'label' => array('zh_cn' => '关闭', 'en' => 'Close'),    'icon' => 'off',   'branch' => null),
                'activate' => array('toStatus' => 'active',   'label' => array('zh_cn' => '激活', 'en' => 'Activate'), 'icon' => 'play',  'branch' => null),
            );
        }
        elseif($objectType === 'bug')
        {
            $lifecycleActions = array(
                'resolve'  => array('toStatus' => 'resolved', 'label' => array('zh_cn' => '解决', 'en' => 'Resolve'),  'icon' => 'check', 'branch' => null),
                'close'    => array('toStatus' => 'closed',   'label' => array('zh_cn' => '关闭', 'en' => 'Close'),    'icon' => 'off',   'branch' => null),
                'activate' => array('toStatus' => 'active',   'label' => array('zh_cn' => '激活', 'en' => 'Activate'), 'icon' => 'play',  'branch' => null),
            );
        }
        elseif($objectType === 'task')
        {
            $lifecycleActions = array(
                'close'    => array('toStatus' => 'closed',   'label' => array('zh_cn' => '关闭', 'en' => 'Close'),    'icon' => 'off',   'branch' => null),
                'activate' => array('toStatus' => 'doing',    'label' => array('zh_cn' => '激活', 'en' => 'Activate'), 'icon' => 'play',  'branch' => null),
            );
        }

        if(empty($lifecycleActions)) return $transitions;

        /* Build a set of existing (fromStatus, action) pairs to avoid duplicates. */
        $existingPairs = array();
        foreach($transitions as $tr)
        {
            $pairKey = ($tr['fromStatus'] ?? '') . '|' . ($tr['action'] ?? '');
            $existingPairs[$pairKey] = true;
        }

        /* Find terminal statuses (category='terminal') — skip injecting outgoing transitions for them. */
        $terminalStatuses = array();
        foreach($def['statuses'] as $s)
        {
            if(($s['category'] ?? 'normal') === 'terminal') $terminalStatuses[$s['key']] = true;
        }

        /* For each non-terminal status, inject missing lifecycle transitions. */
        $maxOrder = 0;
        foreach($transitions as $tr)
        {
            $order = (int)($tr['buttonOrder'] ?? 0);
            if($order > $maxOrder) $maxOrder = $order;
        }

        foreach($def['statuses'] as $s)
        {
            $statusKey = $s['key'] ?? '';
            if($statusKey === '' || isset($terminalStatuses[$statusKey])) continue;

            foreach($lifecycleActions as $action => $config)
            {
                $pairKey = $statusKey . '|' . $action;
                if(isset($existingPairs[$pairKey])) continue;

                $maxOrder++;
                $branchSuffix = $config['branch'] === null ? '' : '-' . $config['branch'];
                $transitions[] = array(
                    'key'             => $statusKey . '-to-' . $config['toStatus'] . '-via-' . $action . $branchSuffix,
                    'fromStatus'      => $statusKey,
                    'toStatus'        => $config['toStatus'],
                    'action'          => $action,
                    'branch'          => $config['branch'],
                    'label'           => $config['label'],
                    'roles'           => array(),
                    'accounts'        => array(),
                    'requireComment'  => false,
                    'enabled'         => true,
                    'isCustom'        => false,
                    'buttonLabel'     => $config['label'],
                    'buttonIcon'      => $config['icon'],
                    'buttonOrder'     => $maxOrder,
                    'buttonGroup'     => 'primary',
                    'sideEffects'     => array(),
                    'condition'       => null,
                );
                $existingPairs[$pairKey] = true;
            }
        }

        return $transitions;
    }

    /**
     * Find the matching transitions for a (fromStatus, action[, branch]) query.
     *
     * Returns array of matching enabled transitions. Caller decides what to do with multiple.
     *
     * @param  array       $definition
     * @param  string      $fromStatus
     * @param  string      $action
     * @param  string|null $branch
     * @access protected
     * @return array
     */
    protected function findTransitions(array $definition, string $fromStatus, string $action, ?string $branch): array
    {
        $matches = array();
        foreach($definition['transitions'] ?? array() as $tr)
        {
            if(!$tr['enabled']) continue;
            if($tr['fromStatus'] !== $fromStatus) continue;
            if($tr['action'] !== $action) continue;

            /* Branch matching: if branch is null on either side, treat as wildcard only when both null. */
            $trBranch = array_key_exists('branch', $tr) ? $tr['branch'] : null;
            if($branch === null)
            {
                if($trBranch === null)
                {
                    $matches[] = $tr;
                }
                /* else: this transition has a specific branch but caller didn't specify — skip in exact mode,
                   but we'll collect branch-specific ones separately for ambiguous detection. */
            }
            else
            {
                if($trBranch === $branch) $matches[] = $tr;
            }
        }

        /* If no exact matches and caller didn't specify branch, collect ALL branch variants for ambiguousBranch error. */
        if(empty($matches) && $branch === null)
        {
            foreach($definition['transitions'] ?? array() as $tr)
            {
                if(!$tr['enabled']) continue;
                if($tr['fromStatus'] !== $fromStatus) continue;
                if($tr['action'] !== $action) continue;
                $matches[] = $tr;
            }
        }

        return $matches;
    }

    /**
     * Check if the actor is allowed by the transition.
     *
     * @param  array       $transition
     * @param  object|null $actor   if null, falls back to global $app->user
     * @access protected
     * @return bool
     */
    protected function checkActor(array $transition, ?object $actor): bool
    {
        if($actor === null)
        {
            global $app;
            $actor = $app->user ?? null;
        }
        if($actor === null) return true; /* No actor context — fail open (avoid blocking CLI/install). */

        /* ZenTao convention: super admin (user->admin=true) bypasses role/account restrictions.
           Admin is the system integrator and must always be able to configure/troubleshoot. */
        if(!empty($actor->admin)) return true;

        $accounts = $transition['accounts'] ?? array();
        $roles    = $transition['roles']    ?? array();
        if(empty($accounts) && empty($roles)) return true;

        if(!empty($accounts))
        {
            return in_array($actor->account ?? '', $accounts, true);
        }
        /* roles check: user may have multiple roles. */
        $userRoles = $this->getUserRoles($actor);
        foreach($roles as $role)
        {
            if(in_array($role, $userRoles, true)) return true;
        }
        return false;
    }

    /**
     * Get all roles for a user (primary role + group roles).
     *
     * @param  object $actor
     * @access protected
     * @return array
     */
    protected function getUserRoles(object $actor): array
    {
        $roles = array();
        if(!empty($actor->role)) $roles[] = $actor->role;

        /* Look up group roles if account present. */
        if(!empty($actor->account))
        {
            try
            {
                $groupRoles = $this->dao->select('t2.role')->from(TABLE_USERGROUP)->alias('t1')
                    ->leftJoin(TABLE_GROUP)->alias('t2')->on('t1.group = t2.id')
                    ->where('t1.account')->eq($actor->account)
                    ->fetchPairs();
                foreach($groupRoles as $role)
                {
                    if(!empty($role) && !in_array($role, $roles, true)) $roles[] = $role;
                }
            }
            catch(Throwable $e) { /* ignore DAO errors during install/CLI */ }
        }
        return $roles;
    }

    /**
     * Check comment requirement.
     *
     * @param  array  $transition
     * @param  string $comment
     * @access protected
     * @return bool
     */
    protected function checkComment(array $transition, string $comment): bool
    {
        if(empty($transition['requireComment'])) return true;
        return trim($comment) !== '';
    }

    /**
     * Normalize a label structure.
     *
     * @param  mixed       $label
     * @param  string|null $fallbackKey
     * @access private
     * @return array
     */
    private function normalizeLabel(mixed $label, ?string $fallbackKey): array
    {
        if(!is_array($label)) $label = array();
        if(empty($label))
        {
            return array('zh_cn' => $fallbackKey ?? '', 'en' => $fallbackKey ?? '');
        }
        if(!isset($label['zh_cn'])) $label['zh_cn'] = $label['en'] ?? $fallbackKey ?? '';
        if(!isset($label['en']))    $label['en']    = $label['zh_cn'] ?? $fallbackKey ?? '';
        return $label;
    }

    /**
     * Normalize to a known enum value with fallback.
     *
     * @param  mixed $value
     * @param  array $allowed
     * @param  mixed $fallback
     * @access private
     * @return mixed
     */
    private function normalizeEnum(mixed $value, array $allowed, mixed $fallback): mixed
    {
        if(in_array($value, $allowed, true)) return $value;
        return $fallback;
    }

    /**
     * Normalize to a unique, trimmed string array.
     *
     * @param  mixed $arr
     * @access private
     * @return array
     */
    private function normalizeStringArray(mixed $arr): array
    {
        if(!is_array($arr)) return array();
        $out = array();
        foreach($arr as $v)
        {
            if(!is_string($v)) continue;
            $v = trim($v);
            if($v === '') continue;
            if(!in_array($v, $out, true)) $out[] = $v;
        }
        return $out;
    }
}

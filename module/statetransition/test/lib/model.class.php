<?php
declare(strict_types = 1);

require_once dirname(__FILE__, 5) . '/test/lib/test.class.php';

class statetransitionModelTest extends baseTest
{
    protected $moduleName = 'statetransition';
    protected $className  = 'model';

    /**
     * Get default definition for an objectType.
     *
     * @param  string $objectType
     * @access public
     * @return array
     */
    public function getDefaultDefinitionTest(string $objectType): array
    {
        $result = $this->instance->getDefaultDefinition($objectType);
        if(dao::isError()) return dao::getError();
        return $result;
    }

    /**
     * Validate a definition.
     *
     * @param  array  $definition
     * @param  string $objectType
     * @access public
     * @return array
     */
    public function validateDefinitionTest(array $definition, string $objectType): array
    {
        $result = $this->instance->validateDefinition($definition, $objectType);
        if(dao::isError()) return dao::getError();
        return $result;
    }

    /**
     * Normalize a definition.
     *
     * @param  array  $definition
     * @param  string $objectType
     * @access public
     * @return array
     */
    public function normalizeDefinitionTest(array $definition, string $objectType): array
    {
        $result = $this->instance->normalizeDefinition($definition, $objectType);
        if(dao::isError()) return dao::getError();
        return $result;
    }

    /**
     * Run transition().
     *
     * Returns array with ok/toStatus/errorKey/wasUnrestricted for assertion.
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  int     $objectID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @param  string  $comment
     * @access public
     * @return array
     */
    public function transitionTest(string $objectType, int $productID, int $objectID, string $fromStatus, string $action, ?string $branch = null, string $comment = ''): array
    {
        $decision = $this->instance->transition($objectType, $productID, $objectID, $fromStatus, $action, $branch, $comment);
        return array(
            'ok'              => $decision->ok,
            'toStatus'        => $decision->toStatus,
            'errorKey'        => $decision->errorKey,
            'wasUnrestricted' => $decision->wasUnrestricted,
        );
    }

    /**
     * Run assertStatusChange().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  int    $objectID
     * @param  string $fromStatus
     * @param  string $toStatus
     * @param  string $comment
     * @access public
     * @return array
     */
    public function assertStatusChangeTest(string $objectType, int $productID, int $objectID, string $fromStatus, string $toStatus, string $comment = ''): array
    {
        $decision = $this->instance->assertStatusChange($objectType, $productID, $objectID, $fromStatus, $toStatus, $comment);
        return array(
            'ok'              => $decision->ok,
            'toStatus'        => $decision->toStatus,
            'errorKey'        => $decision->errorKey,
            'wasUnrestricted' => $decision->wasUnrestricted,
        );
    }

    /**
     * Run isActionAllowed().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $fromStatus
     * @param  string $action
     * @access public
     * @return bool
     */
    public function isActionAllowedTest(string $objectType, int $productID, string $fromStatus, string $action): bool
    {
        return $this->instance->isActionAllowed($objectType, $productID, $fromStatus, $action);
    }

    /**
     * Run assertEntryState().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $status
     * @access public
     * @return string
     */
    public function assertEntryStateTest(string $objectType, int $productID, string $status): string
    {
        return $this->instance->assertEntryState($objectType, $productID, $status);
    }

    /**
     * Run getStatusList().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array
     */
    public function getStatusListTest(string $objectType, int $productID): array
    {
        return $this->instance->getStatusList($objectType, $productID);
    }

    /**
     * Run getCustomButtons().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  string $fromStatus
     * @access public
     * @return array
     */
    public function getCustomButtonsTest(string $objectType, int $productID, string $fromStatus): array
    {
        return $this->instance->getCustomButtons($objectType, $productID, $fromStatus);
    }

    /**
     * Run renderMermaid().
     *
     * @param  array  $definition
     * @param  string $currentStatus
     * @access public
     * @return string
     */
    public function renderMermaidTest(array $definition, string $currentStatus = ''): string
    {
        return $this->instance->renderMermaid($definition, $currentStatus);
    }

    /**
     * Run saveDefinition() and return summary.
     *
     * @param  string $objectType
     * @param  int    $productID
     * @param  array  $definition
     * @param  int    $version
     * @param  bool   $enabled
     * @access public
     * @return array
     */
    public function saveDefinitionTest(string $objectType, int $productID, array $definition, int $version = 0, bool $enabled = true): array
    {
        return $this->instance->saveDefinition($objectType, $productID, $definition, $version, $enabled);
    }

    /**
     * Run getDefinition().
     *
     * @param  string $objectType
     * @param  int    $productID
     * @access public
     * @return array|null
     */
    public function getDefinitionTest(string $objectType, int $productID): array|null
    {
        return $this->instance->getDefinition($objectType, $productID);
    }

    /**
     * Run getDefaultToStatus().
     *
     * @param  string  $objectType
     * @param  int     $productID
     * @param  string  $fromStatus
     * @param  string  $action
     * @param  ?string $branch
     * @access public
     * @return string
     */
    public function getDefaultToStatusTest(string $objectType, int $productID, string $fromStatus, string $action, ?string $branch = null): string
    {
        return $this->instance->getDefaultToStatus($objectType, $productID, $fromStatus, $action, $branch);
    }
}

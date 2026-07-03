<?php
declare(strict_types=1);
/**
 * The transitionDecision value object.
 *
 * Returned by statetransitionModel::transition() and assertStatusChange().
 *
 * Design (PRD §5.3):
 *  - $ok:              whether the transition is allowed
 *  - $toStatus:        when ok=true, the resolved target status (always sourced from configuration,
 *                      never decided by business code). When ok=false, ''.
 *  - $transition:      when ok=true, the matched transition row (array). When ok=false, null.
 *  - $errorKey:        when ok=false, an i18n key (see lang/statetransition/errors)
 *  - $errorMessage:    when ok=false, the translated error message
 *  - $wasUnrestricted: true if the workflow did not constrain this transition
 *                      (definition not enabled / not found). Used for auditing gray-rollouts.
 */
final class transitionDecision
{
    public bool   $ok;
    public string $toStatus;
    public ?array $transition;
    public string $errorKey;
    public string $errorMessage;
    public bool   $wasUnrestricted;

    public function __construct(array $args = array())
    {
        $this->ok              = $args['ok']              ?? false;
        $this->toStatus        = $args['toStatus']        ?? '';
        $this->transition      = $args['transition']      ?? null;
        $this->errorKey        = $args['errorKey']        ?? '';
        $this->errorMessage    = $args['errorMessage']    ?? '';
        $this->wasUnrestricted = $args['wasUnrestricted'] ?? false;
    }

    /**
     * Build a successful decision.
     */
    public static function ok(string $toStatus, ?array $transition, bool $wasUnrestricted = false): transitionDecision
    {
        return new self(array(
            'ok'              => true,
            'toStatus'        => $toStatus,
            'transition'      => $transition,
            'wasUnrestricted' => $wasUnrestricted,
        ));
    }

    /**
     * Build a failure decision.
     */
    public static function fail(string $errorKey, string $errorMessage): transitionDecision
    {
        return new self(array(
            'ok'           => false,
            'errorKey'     => $errorKey,
            'errorMessage' => $errorMessage,
        ));
    }
}

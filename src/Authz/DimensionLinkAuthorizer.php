<?php

namespace Platform\Organization\Authz;

use Platform\Core\Authz\AuthzResolver;
use Platform\Core\Models\User;

/**
 * Gemeinsame Authz-Entscheidungen für DimensionLink-Oberflächen (Terminal-App,
 * MCP-Tools, DimensionLinker). Durchsetzung an der Grenze, nicht im Service —
 * on-grain mit Planner (Policy nutzt may()||owns(), Listen reachableEntityIds).
 *
 * Konvention:
 *  - Entity-Sichtbarkeit/Reichweite: über den Org-Graphen (reachableEntityIds),
 *    NICHT may(), da Entities selbst nicht in authz_resource_link hängen.
 *  - Kontext-Objekt (Projekt, Deal, …): may()||owns() wie die jeweilige Policy.
 */
class DimensionLinkAuthorizer
{
    private AuthzResolver $resolver;

    public function __construct(?AuthzResolver $resolver = null)
    {
        $this->resolver = $resolver ?? app(AuthzResolver::class);
    }

    /**
     * Entity-IDs, die der User lesen darf (für Read-Filter der Links).
     *
     * @return array<int, int>
     */
    public function readableEntityIds(User $user): array
    {
        return $this->resolver->reachableEntityIds($user, 'read');
    }

    /**
     * Entity-IDs, die der User beschreiben darf (für den Add-Link-Picker in v2).
     *
     * @return array<int, int>
     */
    public function writableEntityIds(User $user): array
    {
        return $this->resolver->reachableEntityIds($user, 'write');
    }

    /**
     * Darf der User das Kontext-Objekt verändern (Links anhängen/lösen)?
     * Objekt-Write ist das maßgebliche Recht — man editiert die Zuordnungen
     * genau dieses Objekts (analog zur update()-Policy des Moduls).
     */
    public function canWriteContext(User $user, string $contextType, int $contextId): bool
    {
        return $this->resolver->may($user, 'write', $contextType, $contextId)
            || $this->resolver->owns($user, $contextType, $contextId);
    }
}

<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Collection;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;

/**
 * Perspektive = aus Sicht welcher Carrier-Entity wir gerade lesen.
 *
 * Reiner UI-State (Session). Keine DB-Persistenz noetig — datentechnisch
 * Relevantes (entity_vsm_assignments.perspective_entity_id,
 * signals.perspective_entity_id) traegt seine Sicht selbst.
 */
class PerspectiveService
{
    public const SESSION_KEY = 'current_perspective_entity_id';

    /**
     * Aktive Perspektiv-Entity der Session. Fallback = Default = Root-Carrier des Teams.
     */
    public static function getActiveEntity(int $teamId, ?int $userId = null): ?OrganizationEntity
    {
        $sessionEntityId = session(self::SESSION_KEY);
        if ($sessionEntityId) {
            $entity = OrganizationEntity::with('type')
                ->where('id', $sessionEntityId)
                ->where('team_id', $teamId)
                ->first();
            if ($entity && $entity->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER) {
                return $entity;
            }
            session()->forget(self::SESSION_KEY);
        }

        return self::getDefaultEntity($teamId);
    }

    /**
     * Default-Perspektive = Root-Carrier des Teams (das oberste lebensfaehige System).
     */
    public static function getDefaultEntity(int $teamId): ?OrganizationEntity
    {
        return OrganizationEntity::with('type')
            ->forTeam($teamId)
            ->whereNull('parent_entity_id')
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->orderBy('id')
            ->first();
    }

    /**
     * Switch in Session. Lehnt Nicht-Carrier ab.
     */
    public static function setActiveEntity(int $entityId, int $teamId): ?OrganizationEntity
    {
        $entity = OrganizationEntity::with('type')
            ->where('id', $entityId)
            ->where('team_id', $teamId)
            ->first();

        if (!$entity || $entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
            return null;
        }

        session([self::SESSION_KEY => $entity->id]);

        return $entity;
    }

    /**
     * Alle Carrier-Entities eines Teams als waehlbare Perspektiven.
     */
    public static function getCarriersForTeam(int $teamId): Collection
    {
        return OrganizationEntity::with('type')
            ->forTeam($teamId)
            ->active()
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->orderBy('name')
            ->get();
    }
}

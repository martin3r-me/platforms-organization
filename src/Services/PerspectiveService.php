<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationPerspectiveTeam;

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
     * Default-Perspektive eines Teams.
     *
     * Kaskade:
     *  1. Explizit per organization_perspective_teams.is_default markiert
     *  2. Root-Carrier des Teams (Convention: hoechste lebensfaehige Einheit)
     *
     * Eine ueber das Mapping zugewiesene Default-Perspektive kann auch
     * fremd-Team sein (z.B. Holding-Sicht BHG.DIGITAL als Default fuer
     * Tochter-Team RHEINGEDECK) — das ist explizit gewollt.
     */
    public static function getDefaultEntity(int $teamId): ?OrganizationEntity
    {
        $mappedId = OrganizationPerspectiveTeam::query()
            ->forTeam($teamId)
            ->default()
            ->value('perspective_entity_id');

        if ($mappedId) {
            $entity = OrganizationEntity::with('type')
                ->where('id', $mappedId)
                ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
                ->first();
            if ($entity) {
                return $entity;
            }
        }

        return OrganizationEntity::with('type')
            ->forTeam($teamId)
            ->whereNull('parent_entity_id')
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->orderBy('id')
            ->first();
    }

    /**
     * Setzt eine Perspektive als Default fuer ein Team.
     * Vorhandene Defaults im selben Team werden abgeraeumt (Single-Default-Constraint).
     * Falls noch kein Mapping besteht, wird es angelegt.
     */
    public static function setTeamDefault(int $perspectiveEntityId, int $teamId): ?OrganizationPerspectiveTeam
    {
        $perspective = OrganizationEntity::with('type')
            ->where('id', $perspectiveEntityId)
            ->first();
        if (! $perspective || $perspective->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
            return null;
        }

        return DB::transaction(function () use ($perspectiveEntityId, $teamId) {
            OrganizationPerspectiveTeam::query()
                ->forTeam($teamId)
                ->default()
                ->update(['is_default' => false]);

            return OrganizationPerspectiveTeam::updateOrCreate(
                ['perspective_entity_id' => $perspectiveEntityId, 'team_id' => $teamId],
                ['is_default' => true],
            );
        });
    }

    /**
     * Liste aller Teams, denen eine Perspektive zugewiesen ist.
     */
    public static function getTeamsForPerspective(int $perspectiveEntityId): \Illuminate\Support\Collection
    {
        return OrganizationPerspectiveTeam::query()
            ->forPerspective($perspectiveEntityId)
            ->with('team:id,name')
            ->get()
            ->map(fn ($pt) => [
                'team_id' => $pt->team_id,
                'team_name' => $pt->team?->name,
                'is_default' => (bool) $pt->is_default,
            ]);
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

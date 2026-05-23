<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Collection;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationPerspective;

class PerspectiveService
{
    /**
     * Get all entities that have at least one dimension assignment in this perspective.
     * A perspective is a flat collection of dimension-links — an entity is "in view"
     * if it has any dimension_link with this perspective_id.
     */
    public function entitiesInView(OrganizationPerspective $perspective): Collection
    {
        $entityIds = OrganizationDimensionLink::where('perspective_id', $perspective->id)
            ->where('linkable_type', 'organization_entity')
            ->pluck('linkable_id')
            ->unique();

        return OrganizationEntity::whereIn('id', $entityIds)
            ->with(['type', 'parent'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get dimension assignments for a specific entity within a perspective.
     * Returns universal (perspective_id = null) + perspective-specific links.
     */
    public function getDimensionsForEntity(int $entityId, OrganizationPerspective $perspective): \Illuminate\Support\Collection
    {
        return OrganizationDimensionLink::where('linkable_type', 'organization_entity')
            ->where('linkable_id', $entityId)
            ->forPerspective($perspective->id)
            ->with(['definition', 'value'])
            ->get()
            ->groupBy(fn ($link) => $link->definition?->key);
    }

    /**
     * Check if an entity is visible in a given perspective.
     */
    public function isEntityInView(int $entityId, OrganizationPerspective $perspective): bool
    {
        return OrganizationDimensionLink::where('perspective_id', $perspective->id)
            ->where('linkable_type', 'organization_entity')
            ->where('linkable_id', $entityId)
            ->exists();
    }

    /**
     * Get the active perspective for a user in a team.
     * Priority: session override → team default → auto-create default.
     */
    public static function getActive(int $teamId, ?int $userId = null): OrganizationPerspective
    {
        // 1. Session override
        $sessionPerspectiveId = session('current_perspective_id');
        if ($sessionPerspectiveId) {
            $perspective = OrganizationPerspective::where('id', $sessionPerspectiveId)
                ->where('team_id', $teamId)
                ->first();
            if ($perspective) {
                return $perspective;
            }
            // Invalid session value — clear it
            session()->forget('current_perspective_id');
        }

        // 2. Team default (or auto-create)
        return OrganizationPerspective::getOrCreateDefault($teamId, $userId);
    }

    /**
     * Switch the active perspective for the current session.
     */
    public static function switchTo(int $perspectiveId, int $teamId): ?OrganizationPerspective
    {
        $perspective = OrganizationPerspective::where('id', $perspectiveId)
            ->where('team_id', $teamId)
            ->first();

        if (!$perspective) {
            return null;
        }

        session(['current_perspective_id' => $perspective->id]);

        return $perspective;
    }

    /**
     * Get all available perspectives for a team.
     */
    public static function getForTeam(int $teamId): Collection
    {
        return OrganizationPerspective::where('team_id', $teamId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}

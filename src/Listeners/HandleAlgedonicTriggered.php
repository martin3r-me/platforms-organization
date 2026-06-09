<?php

namespace Platform\Organization\Listeners;

use Platform\Core\Events\AlgedonicTriggered;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\PerspectiveService;

/**
 * Algedonic-Listener: empfaengt das vom Core dispatchete
 * AlgedonicTriggered-Event und erzeugt daraus ein OrganizationSignal,
 * das alle Routing-Stufen ueberspringt und direkt auf S5 zielt.
 *
 * Konventionen (Beer):
 *  - vsm_level = s5  (geht direkt nach oben)
 *  - source_type = human_algedonic
 *  - severity = critical
 *  - deadline_at = now() + 1h  (kurz, dringend)
 *  - current_owner = S5-Owner der Perspektive, sonst Carrier-Root selbst
 */
class HandleAlgedonicTriggered
{
    public function handle(AlgedonicTriggered $event): void
    {
        $isAnonymous = $event->userId === 0;

        // Perspektive ermitteln. Kaskade:
        //  1. explizit in Event-Payload
        //  2. User-Session-Wahl (nur wenn nicht anonym — sonst wuerde
        //     PerspectiveService die Session des Users lesen)
        //  3. PerspectiveService::getDefaultEntity — beruecksichtigt das
        //     Team-Default-Mapping und faellt sonst auf Root-Carrier zurueck.
        $perspectiveEntityId = $event->perspectiveEntityId
            ?? (! $isAnonymous ? $this->resolveActivePerspective($event->teamId, $event->userId) : null)
            ?? PerspectiveService::getDefaultEntity($event->teamId)?->id;

        if (! $perspectiveEntityId) {
            return; // kein Carrier-Setup im Team — Algedonic landet im Nichts
        }

        $entityId = $event->entityId ?: $perspectiveEntityId;

        $ownerId = $this->resolveS5Owner($perspectiveEntityId)
            ?? $perspectiveEntityId; // Fallback: Carrier-Root selbst ist S5

        $deadlineHours = (int) config('organization.signal_deadlines.algedonic', 1);

        OrganizationSignal::create([
            'team_id' => $event->teamId,
            'source' => 'inference',
            'source_type' => OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC,
            'entity_id' => $entityId,
            'perspective_entity_id' => $perspectiveEntityId,
            'current_owner_entity_id' => $ownerId,
            'vsm_level' => 's5',
            'status' => 'open',
            'severity' => 'critical',
            'message' => $event->message,
            'trigger_metrics' => array_filter([
                'algedonic' => true,
                'anonymous' => $isAnonymous,
                'triggered_by_user_id' => $isAnonymous ? null : $event->userId,
                'triggered_at' => now()->toIso8601String(),
            ], fn ($v) => $v !== null),
            'deadline_at' => now()->copy()->addHours(max(1, $deadlineHours)),
        ]);
    }

    protected function resolveActivePerspective(int $teamId, int $userId): ?int
    {
        $entity = PerspectiveService::getActiveEntity($teamId, $userId);
        return $entity?->id;
    }

    protected function resolveS5Owner(int $perspectiveEntityId): ?int
    {
        $id = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $perspectiveEntityId)
            ->where('vsm_system', 's5')
            ->activeAt()
            ->orderBy('id')
            ->value('assigned_entity_id');

        return $id ? (int) $id : null;
    }
}

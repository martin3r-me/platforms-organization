<?php

namespace Platform\Organization\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationSignal;

/**
 * Eskaliert ueberfaellige Signale entlang der VSM-Stufen und kaskadiert
 * unabsorbierte S5-Signale in die aeussere Carrier-Perspektive (Aggregation).
 *
 * Cron-Aufruf via Console-Command `inference:escalate-signals`.
 */
class SignalEscalationService
{
    /**
     * VSM-Eskalationsreihenfolge ist jetzt zentral am Modell.
     * Aliase fuer kuerzeren Zugriff.
     */
    protected const NEXT_LEVEL = \Platform\Organization\Models\OrganizationEntityVsmAssignment::NEXT_LEVEL;

    protected const SEVERITY_LADDER = [
        'info' => 'warning',
        'warning' => 'critical',
        'critical' => 'algedonic',
        'algedonic' => 'algedonic',
    ];

    public function run(): array
    {
        $escalated = $this->escalateOverdueSignals();
        $aggregated = $this->aggregateUnabsorbedS5();

        return [
            'escalated' => $escalated,
            'aggregated' => $aggregated,
        ];
    }

    /**
     * Eskaliert alle Signale, deren deadline_at ueberschritten und die
     * nicht acknowledged sind. Naechste VSM-Ebene wird gewaehlt, neuer
     * Owner aus dem Assignment-Lookup, neue Deadline gesetzt, Severity
     * eine Stufe hoeher (max algedonic).
     */
    public function escalateOverdueSignals(): int
    {
        $signals = OrganizationSignal::query()
            ->dueForEscalation()
            ->whereNotNull('vsm_level')
            ->whereNull('aggregated_at')
            ->get();

        $count = 0;
        foreach ($signals as $signal) {
            $currentLevel = $signal->vsm_level;
            $nextLevel = self::NEXT_LEVEL[$currentLevel] ?? null;

            if ($nextLevel === null) {
                // s5 erreicht — Aggregation uebernimmt aggregateUnabsorbedS5().
                continue;
            }

            $newOwner = $this->resolveOwner($signal->perspective_entity_id, $nextLevel);
            $deadlineHours = $this->deadlineHoursFor($nextLevel);
            $newSeverity = self::SEVERITY_LADDER[$signal->severity] ?? $signal->severity;

            $signal->update([
                'vsm_level' => $nextLevel,
                'current_owner_entity_id' => $newOwner,
                'escalated_at' => now(),
                'deadline_at' => now()->copy()->addHours($deadlineHours),
                'severity' => $newSeverity,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Wenn ein Signal auf s5 ueberfaellig + nicht acknowledged + noch nicht
     * aggregiert: erzeuge ein neues Signal in der Parent-Carrier-Perspektive
     * mit source_type='aggregation'. Original wird mit aggregated_at +
     * aggregated_to_signal_id markiert, bleibt aber offen.
     */
    public function aggregateUnabsorbedS5(): int
    {
        $signals = OrganizationSignal::query()
            ->where('vsm_level', 's5')
            ->dueForEscalation()
            ->whereNull('aggregated_at')
            ->whereNotNull('perspective_entity_id')
            ->with('perspectiveEntity.parent.type')
            ->get();

        $count = 0;
        foreach ($signals as $signal) {
            $parentCarrier = $this->findParentCarrier($signal->perspectiveEntity);
            if (!$parentCarrier) {
                // Wir sind am Top-Carrier — keine aeussere Perspektive mehr.
                // Severity auf algedonic forcieren wenn nicht schon dort.
                if ($signal->severity !== 'algedonic') {
                    $signal->update(['severity' => 'algedonic']);
                }
                continue;
            }

            // Idempotenz: pruefen ob es schon ein offenes Aggregations-Signal
            // fuer (parent, entity, source_type=aggregation) gibt.
            $existing = OrganizationSignal::query()
                ->where('team_id', $signal->team_id)
                ->where('entity_id', $signal->entity_id)
                ->where('perspective_entity_id', $parentCarrier->id)
                ->where('source_type', OrganizationSignal::SOURCE_TYPE_AGGREGATION)
                ->whereIn('status', ['open', 'acknowledged'])
                ->first();

            if ($existing) {
                $signal->update([
                    'aggregated_at' => now(),
                    'aggregated_to_signal_id' => $existing->id,
                ]);
                continue;
            }

            $outerOwner = $this->resolveOwner($parentCarrier->id, 's5');
            $deadlineHours = $this->deadlineHoursFor('s5');

            $outerSignal = OrganizationSignal::create([
                'team_id' => $signal->team_id,
                'source' => 'inference',
                'source_type' => OrganizationSignal::SOURCE_TYPE_AGGREGATION,
                'inference_prompt_id' => $signal->inference_prompt_id,
                'entity_id' => $signal->entity_id,
                'perspective_entity_id' => $parentCarrier->id,
                'created_by_agent_entity_id' => $signal->created_by_agent_entity_id,
                'current_owner_entity_id' => $outerOwner,
                'vsm_level' => 's5',
                'status' => 'open',
                'severity' => 'algedonic',
                'message' => '[Aggregation] ' . $signal->message,
                'trigger_metrics' => array_merge(
                    is_array($signal->trigger_metrics) ? $signal->trigger_metrics : [],
                    [
                        '_aggregation' => [
                            'from_signal_id' => $signal->id,
                            'from_perspective_id' => $signal->perspective_entity_id,
                            'reason' => 'S5 in innerer Perspektive nicht absorbiert',
                        ],
                    ]
                ),
                'escalated_at' => now(),
                'deadline_at' => now()->copy()->addHours($deadlineHours),
            ]);

            $signal->update([
                'aggregated_at' => now(),
                'aggregated_to_signal_id' => $outerSignal->id,
            ]);

            $count++;
        }

        return $count;
    }

    protected function findParentCarrier(?OrganizationEntity $entity): ?OrganizationEntity
    {
        if (!$entity) {
            return null;
        }
        $current = $entity->parent;
        while ($current) {
            if ($current->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER) {
                return $current;
            }
            $current = $current->parent;
        }
        return null;
    }

    protected function resolveOwner(?int $perspectiveEntityId, ?string $vsmLevel): ?int
    {
        if (!$perspectiveEntityId || !$vsmLevel) {
            return null;
        }
        return OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $perspectiveEntityId)
            ->where('vsm_system', $vsmLevel)
            ->activeAt()
            ->orderBy('id')
            ->value('assigned_entity_id');
    }

    protected function deadlineHoursFor(string $level): int
    {
        return (int) config(
            "organization.signal_deadlines.{$level}",
            config('organization.signal_deadlines.default', 168)
        );
    }
}

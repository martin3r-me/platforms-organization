<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSignalsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/signals - Listet ausgelöste Signale (algedonic alerts) im Team. Signale sind VSM-Alarme die auf Probleme hinweisen. Filtere nach entity_id für eine bestimmte Entity, status="open" für offene, severity="critical"/"algedonic" für dringende.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Signale dieser Entity. Beispiel: entity_id=5',
                    ],
                    'include_children' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = auch Signale von Kind-Entities einschließen. Nur wirksam mit entity_id. Default: false.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: open | acknowledged | resolved | dismissed. Beispiel: status="open"',
                        'enum' => ['open', 'acknowledged', 'resolved', 'dismissed'],
                    ],
                    'severity' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: info | warning | critical | algedonic. Beispiel: severity="critical"',
                        'enum' => ['info', 'warning', 'critical', 'algedonic'],
                    ],
                    'source' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: rule (regelbasiert) | inference (KI-generiert).',
                        'enum' => ['rule', 'inference'],
                    ],
                    'signal_definition_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Signale dieser Definition.',
                    ],
                    'include_snoozed' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = auch gesnoozde Signale anzeigen. Default: false (nur actionable Signale).',
                    ],
                    'assignee_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Signale mit diesem Assignee.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationSignal::query()
                ->where('team_id', $rootTeamId)
                ->with(['entity:id,name', 'definition:id,name,pattern_type', 'inferencePrompt:id,name,vsm_system', 'assignee:id,name']);

            // Default: only actionable signals (exclude snoozed)
            if (empty($arguments['include_snoozed']) && empty($arguments['status'])) {
                $q->actionable();
            } elseif (! empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            if (! empty($arguments['severity'])) {
                $q->where('severity', $arguments['severity']);
            }

            if (! empty($arguments['entity_id'])) {
                $entityId = (int) $arguments['entity_id'];

                if (!empty($arguments['include_children'])) {
                    $childIds = OrganizationEntity::where('parent_entity_id', $entityId)
                        ->where('team_id', $rootTeamId)
                        ->where('is_active', true)
                        ->pluck('id')
                        ->toArray();
                    $q->whereIn('entity_id', array_merge([$entityId], $childIds));
                } else {
                    $q->where('entity_id', $entityId);
                }
            }

            if (! empty($arguments['signal_definition_id'])) {
                $q->where('signal_definition_id', (int) $arguments['signal_definition_id']);
            }

            if (! empty($arguments['source'])) {
                $q->where('source', $arguments['source']);
            }

            if (! empty($arguments['assignee_entity_id'])) {
                $q->where('assignee_entity_id', (int) $arguments['assignee_entity_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
            $this->applyStandardSearch($q, $arguments, ['message']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'severity', 'status'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($signal) => [
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'entity_id' => $signal->entity_id,
                'entity_name' => $signal->entity?->name,
                'source' => $signal->source ?? 'rule',
                'signal_definition_id' => $signal->signal_definition_id,
                'definition_name' => $signal->definition?->name,
                'pattern_type' => $signal->definition?->pattern_type,
                'inference_prompt_id' => $signal->inference_prompt_id,
                'inference_prompt_name' => $signal->inferencePrompt?->name,
                'inference_vsm_system' => $signal->inferencePrompt?->vsm_system,
                'status' => $signal->status,
                'severity' => $signal->severity,
                'message' => $signal->message,
                'trigger_metrics' => $signal->trigger_metrics,
                'snooze_until' => $signal->snooze_until?->toIso8601String(),
                'assignee_entity_id' => $signal->assignee_entity_id,
                'assignee_name' => $signal->assignee?->name,
                'affected_entity_ids' => $signal->affected_entity_ids,
                'resolved_at' => $signal->resolved_at?->toIso8601String(),
                'created_at' => $signal->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Signale: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'signals', 'algedonic', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

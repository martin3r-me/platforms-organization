<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;

class ListVsmAssignmentsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_assignments.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/vsm-assignments - Listet VSM-Zellen-Besetzungen. Filter: perspective_entity_id (Carrier, aus dessen Sicht), vsm_system (s1/s2/s3/s3_star/s4/s5), assigned_entity_id (Actor). active_only=true beschraenkt auf heute gueltige Eintraege.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Carrier-Entity, aus deren Sicht gelistet werden soll.',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'Optional: VSM-Ebene (s1, s2, s3, s3_star, s4, s5).',
                ],
                'assigned_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Actor-Entity, deren Zuordnungen gelistet werden sollen (z.B. um zu sehen, wo eine Person ueberall VSM-Rollen hat).',
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur heute gueltige Zuordnungen (valid_from/valid_until pruefen). Default: false.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Anzahl. Default: 100.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext.');
            }

            $query = OrganizationEntityVsmAssignment::query()
                ->where('team_id', $teamId)
                ->with([
                    'perspectiveEntity:id,name,code',
                    'assignedEntity:id,name,code',
                    'assignedEntity.type:id,name,code,vsm_class',
                ]);

            if (!empty($arguments['perspective_entity_id'])) {
                $query->forPerspective((int) $arguments['perspective_entity_id']);
            }
            if (!empty($arguments['vsm_system'])) {
                $query->forSystem((string) $arguments['vsm_system']);
            }
            if (!empty($arguments['assigned_entity_id'])) {
                $query->forAssignee((int) $arguments['assigned_entity_id']);
            }
            if (!empty($arguments['active_only'])) {
                $query->activeAt();
            }

            $limit = (int) ($arguments['limit'] ?? 100);
            $items = $query->orderBy('perspective_entity_id')
                ->orderBy('vsm_system')
                ->orderBy('assigned_entity_id')
                ->limit(min($limit, 500))
                ->get();

            $data = $items->map(fn ($a) => [
                'id' => $a->id,
                'uuid' => $a->uuid,
                'perspective_entity_id' => $a->perspective_entity_id,
                'perspective_name' => $a->perspectiveEntity?->name,
                'vsm_system' => $a->vsm_system,
                'assigned_entity_id' => $a->assigned_entity_id,
                'assigned_name' => $a->assignedEntity?->name,
                'assigned_type' => $a->assignedEntity?->type?->name,
                'scope' => $a->scope,
                'valid_from' => $a->valid_from?->toDateString(),
                'valid_until' => $a->valid_until?->toDateString(),
                'notes' => $a->notes,
                'is_active_today' => $a->isActiveAt(),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der VSM-Zuordnungen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'vsm', 'assignments'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

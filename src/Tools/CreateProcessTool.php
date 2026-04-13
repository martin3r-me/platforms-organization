<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.processes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/processes - Erstellt eine Prozess-Definition. Status: draft | active | deprecated.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'name'            => ['type' => 'string', 'description' => 'ERFORDERLICH: Name des Prozesses.'],
                'code'            => ['type' => 'string', 'description' => 'Optional: Kurz-Code (z.B. "P-001").'],
                'description'     => ['type' => 'string'],
                'owner_entity_id' => ['type' => 'integer', 'description' => 'Optional: Owner-Entity (Abteilung, Person, etc.).'],
                'vsm_system_id'   => ['type' => 'integer', 'description' => 'Optional: VSM-System-Zuordnung.'],
                'process_group_id' => ['type' => 'integer', 'description' => 'Optional: Prozess-Gruppe für thematisches Clustering.'],
                'status'          => ['type' => 'string', 'description' => 'Optional: draft | active | deprecated. Default: draft.'],
                'version'         => ['type' => 'integer', 'description' => 'Optional: Default 1.'],
                'is_active'       => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                'metadata'        => ['type' => 'object', 'description' => 'Optional: Freie JSON-Metadaten.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $process = OrganizationProcess::create([
                'team_id'         => $rootTeamId,
                'user_id'         => $context->user?->id,
                'name'            => $name,
                'code'            => ($arguments['code'] ?? null) ?: null,
                'description'     => ($arguments['description'] ?? null) ?: null,
                'owner_entity_id' => ! empty($arguments['owner_entity_id']) ? (int) $arguments['owner_entity_id'] : null,
                'vsm_system_id'   => ! empty($arguments['vsm_system_id']) ? (int) $arguments['vsm_system_id'] : null,
                'process_group_id' => ! empty($arguments['process_group_id']) ? (int) $arguments['process_group_id'] : null,
                'status'          => ($arguments['status'] ?? 'draft'),
                'version'         => ($arguments['version'] ?? 1),
                'is_active'       => $arguments['is_active'] ?? true,
                'metadata'        => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'      => $process->id,
                'uuid'    => $process->uuid,
                'name'    => $process->name,
                'code'    => $process->code,
                'status'  => $process->status,
                'team_id' => $process->team_id,
                'message' => 'Prozess erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Prozesses: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}

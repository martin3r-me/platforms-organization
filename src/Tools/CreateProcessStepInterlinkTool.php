<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessStepInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessStepInterlinkTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_interlinks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-step-interlinks - Ordnet einen Interlink einem Prozess-Schritt zu. role: triggers | consumes | produces.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'process_step_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Prozess-Schritt.'],
                'interlink_id'    => ['type' => 'integer', 'description' => 'ERFORDERLICH: Interlink.'],
                'role'            => ['type' => 'string', 'description' => 'ERFORDERLICH: triggers | consumes | produces.'],
                'metadata'        => ['type' => 'object'],
            ],
            'required' => ['process_step_id', 'interlink_id', 'role'],
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

            $processStepId = (int) ($arguments['process_step_id'] ?? 0);
            $interlinkId   = (int) ($arguments['interlink_id'] ?? 0);
            $role          = trim((string) ($arguments['role'] ?? ''));

            if ($processStepId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_step_id ist erforderlich.');
            }
            if ($interlinkId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'interlink_id ist erforderlich.');
            }
            if ($role === '') {
                return ToolResult::error('VALIDATION_ERROR', 'role ist erforderlich.');
            }

            $stepInterlink = OrganizationProcessStepInterlink::create([
                'team_id'         => $rootTeamId,
                'user_id'         => $context->user?->id,
                'process_step_id' => $processStepId,
                'interlink_id'    => $interlinkId,
                'role'            => $role,
                'metadata'        => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'              => $stepInterlink->id,
                'uuid'            => $stepInterlink->uuid,
                'process_step_id' => $stepInterlink->process_step_id,
                'interlink_id'    => $stepInterlink->interlink_id,
                'role'            => $stepInterlink->role,
                'team_id'         => $stepInterlink->team_id,
                'message'         => 'Step-Interlink-Zuordnung erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Step-Interlink-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_interlinks', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}

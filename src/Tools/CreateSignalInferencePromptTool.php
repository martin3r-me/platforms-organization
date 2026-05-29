<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateSignalInferencePromptTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference_prompts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signal_inference_prompts - Erstellt einen diagnostischen Inference-Prompt (VSM System 1–5, 3*). Claude evaluiert diesen gegen Entity-Kontext und erzeugt Inference-Signale.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name des Inference-Prompts.',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: VSM-System (s1, s2, s3, s3_star, s4, s5).',
                    'enum' => ['s1', 's2', 's3', 's3_star', 's4', 's5'],
                ],
                'prompt_template' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Die diagnostische Frage (z.B. "Gibt es Einheiten über die das Management nichts weiß weil keine Daten fließen?").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Prompts.',
                ],
                'data_sources' => [
                    'type' => 'array',
                    'description' => 'Optional: Welche Datenquellen abgefragt werden. Default: ["snapshots", "movement"]. Möglich: snapshots, movement, correspondence, recordings, activity_log.',
                    'items' => ['type' => 'string'],
                ],
                'dimension' => [
                    'type' => 'string',
                    'description' => 'Optional: 7½-Dimension (z.B. quality, time, cost, etc.).',
                ],
                'default_severity' => [
                    'type' => 'string',
                    'description' => 'Optional: Standard-Severity für erzeugte Signale. Default: warning.',
                    'enum' => ['info', 'warning', 'critical'],
                ],
                'scope_type' => [
                    'type' => 'string',
                    'description' => 'Optional: all (default), entity_type, subtree.',
                    'enum' => ['all', 'entity_type', 'subtree'],
                    'default' => 'all',
                ],
                'scope_value' => [
                    'type' => 'array',
                    'description' => 'Optional: Scope-Werte (Type-Codes oder Root-Entity-ID). Nur bei scope_type != all.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['name', 'vsm_system', 'prompt_template'],
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

            $vsmSystem = $arguments['vsm_system'] ?? '';
            if (! in_array($vsmSystem, ['s1', 's2', 's3', 's3_star', 's4', 's5'])) {
                return ToolResult::error('VALIDATION_ERROR', 'vsm_system muss s1, s2, s3, s3_star, s4 oder s5 sein.');
            }

            $promptTemplate = trim((string) ($arguments['prompt_template'] ?? ''));
            if ($promptTemplate === '') {
                return ToolResult::error('VALIDATION_ERROR', 'prompt_template ist erforderlich.');
            }

            $defaultSeverity = $arguments['default_severity'] ?? 'warning';
            if (! in_array($defaultSeverity, ['info', 'warning', 'critical'])) {
                return ToolResult::error('VALIDATION_ERROR', 'default_severity muss info, warning oder critical sein.');
            }

            $scopeType = $arguments['scope_type'] ?? 'all';
            if (! in_array($scopeType, ['all', 'entity_type', 'subtree'])) {
                return ToolResult::error('VALIDATION_ERROR', 'scope_type muss all, entity_type oder subtree sein.');
            }

            $prompt = OrganizationSignalInferencePrompt::create([
                'name' => $name,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string) $arguments['description'] : null,
                'vsm_system' => $vsmSystem,
                'prompt_template' => $promptTemplate,
                'data_sources' => $arguments['data_sources'] ?? ['snapshots', 'movement'],
                'dimension' => $arguments['dimension'] ?? null,
                'default_severity' => $defaultSeverity,
                'scope_type' => $scopeType,
                'scope_value' => $arguments['scope_value'] ?? null,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $prompt->id,
                'uuid' => $prompt->uuid,
                'name' => $prompt->name,
                'vsm_system' => $prompt->vsm_system,
                'prompt_template' => $prompt->prompt_template,
                'data_sources' => $prompt->data_sources,
                'dimension' => $prompt->dimension,
                'default_severity' => $prompt->default_severity,
                'scope_type' => $prompt->scope_type,
                'is_active' => (bool) $prompt->is_active,
                'message' => 'Inference-Prompt erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Inference-Prompts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

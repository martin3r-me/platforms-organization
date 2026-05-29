<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSignalInferencePromptsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference_prompts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/signal_inference_prompts - Listet diagnostische Inference-Prompts (VSM System 1–5, 3*). Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'vsm_system', 'is_active', 'dimension']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'vsm_system' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach VSM-System (s1, s2, s3, s3_star, s4, s5).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive/inaktive Prompts.',
                    ],
                    'dimension' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Dimension.',
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

            $q = OrganizationSignalInferencePrompt::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (! empty($arguments['vsm_system'])) {
                $q->where('vsm_system', $arguments['vsm_system']);
            }

            if (! empty($arguments['dimension'])) {
                $q->where('dimension', $arguments['dimension']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'vsm_system', 'is_active', 'dimension', 'default_severity', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description', 'prompt_template']);
            $this->applyStandardSort($q, $arguments, ['id', 'name', 'created_at', 'vsm_system', 'default_severity'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($prompt) => [
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
                'last_evaluated_at' => $prompt->last_evaluated_at?->toIso8601String(),
                'created_at' => $prompt->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Inference-Prompts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

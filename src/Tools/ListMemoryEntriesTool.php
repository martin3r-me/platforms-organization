<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListMemoryEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.memory.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/memory - Listet Organizational Memory Entries. Filter nach Entity, Memory-Typ, Prompt, Confidence. Zeigt gelernte Baselines, Suppressions, Entity-Profile.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_id', 'memory_type', 'inference_prompt_id', 'source_type', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID.',
                    ],
                    'memory_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Typ (entity_profile, baseline, suppression, relationship, prompt_experience, inquiry_outcome).',
                        'enum' => ['entity_profile', 'baseline', 'suppression', 'relationship', 'prompt_experience', 'inquiry_outcome'],
                    ],
                    'inference_prompt_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Inference-Prompt-ID.',
                    ],
                    'source_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Quelle (signal_feedback, inquiry_response, inference, implicit_feedback, manual).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach aktiven/inaktiven Einträgen. Default: nur aktive.',
                    ],
                    'valid_only' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur nicht-abgelaufene Einträge. Default: true.',
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

            $q = OrganizationMemoryEntry::query()
                ->where('team_id', $rootTeamId)
                ->with(['entity:id,name', 'inferencePrompt:id,name,vsm_system']);

            if (! empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if (! empty($arguments['memory_type'])) {
                $q->where('memory_type', $arguments['memory_type']);
            }

            if (! empty($arguments['inference_prompt_id'])) {
                $q->where('inference_prompt_id', (int) $arguments['inference_prompt_id']);
            }

            if (! empty($arguments['source_type'])) {
                $q->where('source_type', $arguments['source_type']);
            }

            // Default: only active entries
            if (($arguments['is_active'] ?? null) !== false) {
                $q->active();
            }

            // Default: only valid (non-expired) entries
            if (($arguments['valid_only'] ?? true) !== false) {
                $q->valid();
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_id', 'memory_type', 'inference_prompt_id', 'source_type', 'is_active']);
            $this->applyStandardSearch($q, $arguments, ['content']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'confidence', 'memory_type'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($entry) => [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'entity_id' => $entry->entity_id,
                'entity_name' => $entry->entity?->name,
                'inference_prompt_id' => $entry->inference_prompt_id,
                'prompt_name' => $entry->inferencePrompt?->name,
                'memory_type' => $entry->memory_type,
                'content' => $entry->content,
                'structured_data' => $entry->structured_data,
                'confidence' => $entry->confidence,
                'source_type' => $entry->source_type,
                'valid_until' => $entry->valid_until?->toIso8601String(),
                'is_active' => $entry->is_active,
                'reinforcement_count' => $entry->reinforcement_count,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Memory-Entries: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'memory', 'inference', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

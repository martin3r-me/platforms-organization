<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateMemoryEntryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.memory.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/memory - Erzeugt einen Organizational Memory Entry (Baseline, Suppression, Entity-Profile, etc.). Wird genutzt um gelerntes Wissen über die Organisation zu speichern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity-ID für entity-bezogene Memory.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Inference-Prompt-ID für prompt-bezogene Memory.',
                ],
                'memory_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: entity_profile, baseline, suppression, relationship, prompt_experience, inquiry_outcome, source_relevance.',
                    'enum' => ['entity_profile', 'baseline', 'suppression', 'relationship', 'prompt_experience', 'inquiry_outcome', 'source_relevance'],
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Natürlichsprachliche Beschreibung der Erkenntnis.',
                ],
                'structured_data' => [
                    'type' => 'object',
                    'description' => 'Optional: Maschinenlesbare Daten (z.B. Metriken, Schwellwerte).',
                ],
                'confidence' => [
                    'type' => 'number',
                    'description' => 'Optional: Confidence 0.0-1.0. Default: 0.5.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Quelle (signal_feedback, inquiry_response, inference, implicit_feedback, manual). Default: manual.',
                    'enum' => ['signal_feedback', 'inquiry_response', 'inference', 'implicit_feedback', 'manual'],
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Referenz-ID der Quelle (z.B. Signal-ID).',
                ],
                'valid_until' => [
                    'type' => 'string',
                    'description' => 'Optional: Ablaufdatum (ISO 8601). Ohne Angabe: unbegrenzt gültig.',
                ],
            ],
            'required' => ['memory_type', 'content'],
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

            $memoryType = $arguments['memory_type'] ?? '';
            $validTypes = ['entity_profile', 'baseline', 'suppression', 'relationship', 'prompt_experience', 'inquiry_outcome', 'source_relevance'];
            if (! in_array($memoryType, $validTypes)) {
                return ToolResult::error('VALIDATION_ERROR', 'memory_type muss einer von: ' . implode(', ', $validTypes));
            }

            $content = trim((string) ($arguments['content'] ?? ''));
            if ($content === '') {
                return ToolResult::error('VALIDATION_ERROR', 'content ist erforderlich.');
            }

            // Validate entity_id if provided
            $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            if ($entityId) {
                $entity = OrganizationEntity::where('id', $entityId)->where('team_id', $rootTeamId)->first();
                if (! $entity) {
                    return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
                }
            }

            $confidence = (float) ($arguments['confidence'] ?? 0.5);
            $confidence = max(0.0, min(1.0, $confidence));

            $entry = OrganizationMemoryEntry::create([
                'team_id' => $rootTeamId,
                'entity_id' => $entityId,
                'inference_prompt_id' => ! empty($arguments['inference_prompt_id']) ? (int) $arguments['inference_prompt_id'] : null,
                'memory_type' => $memoryType,
                'content' => $content,
                'structured_data' => $arguments['structured_data'] ?? null,
                'confidence' => $confidence,
                'source_type' => $arguments['source_type'] ?? 'manual',
                'source_id' => ! empty($arguments['source_id']) ? (int) $arguments['source_id'] : null,
                'valid_until' => ! empty($arguments['valid_until']) ? $arguments['valid_until'] : null,
                'is_active' => true,
                'reinforcement_count' => 0,
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'memory_type' => $entry->memory_type,
                'confidence' => $entry->confidence,
                'message' => 'Memory-Entry erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Memory-Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'memory', 'inference', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DoNothingInferenceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inference.do_nothing';
    }

    public function getDescription(): string
    {
        return 'Explizites "alles in Ordnung" — keine Anomalien erkannt. Wird protokolliert und optional als Memory-Entry persistiert. Nutze dies wenn die diagnostische Analyse keine Auffälligkeiten ergibt.';
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
                'reason' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Kurze Begründung warum alles in Ordnung ist.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity-ID für die die Entscheidung gilt. Wenn gesetzt, wird ein Memory-Entry erstellt.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Inference-Prompt-ID für Kontext-Zuordnung.',
                ],
            ],
            'required' => ['reason'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($reason === '') {
            return ToolResult::error('VALIDATION_ERROR', 'reason ist erforderlich.');
        }

        $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
        $promptId = ! empty($arguments['inference_prompt_id']) ? (int) $arguments['inference_prompt_id'] : null;

        // Persist as memory entry when entity context is available
        $memoryId = null;
        if ($entityId) {
            try {
                $resolved = $this->resolveTeamAndRoot($arguments, $context);
                if (! $resolved['error']) {
                    $rootTeamId = (int) $resolved['root_team_id'];

                    // Check for existing do_nothing memory for same entity + prompt
                    $query = OrganizationMemoryEntry::where('team_id', $rootTeamId)
                        ->where('entity_id', $entityId)
                        ->where('memory_type', 'entity_profile')
                        ->where('is_active', true)
                        ->whereNotNull('structured_data')
                        ->whereJsonContains('structured_data->do_nothing', true);

                    if ($promptId) {
                        $query->where('inference_prompt_id', $promptId);
                    }

                    $existing = $query->first();

                    if ($existing) {
                        $existing->reinforce(0.05, 7);
                        $memoryId = $existing->id;
                    } else {
                        $entry = OrganizationMemoryEntry::create([
                            'team_id' => $rootTeamId,
                            'entity_id' => $entityId,
                            'inference_prompt_id' => $promptId,
                            'memory_type' => 'entity_profile',
                            'content' => 'Do-Nothing: ' . $reason,
                            'structured_data' => [
                                'do_nothing' => true,
                                'reason' => $reason,
                                'evaluated_at' => now()->toIso8601String(),
                            ],
                            'confidence' => 0.7,
                            'source_type' => 'inference',
                            'valid_until' => now()->addDays(7),
                            'is_active' => true,
                        ]);
                        $memoryId = $entry->id;
                    }
                }
            } catch (\Throwable) {
                // Memory creation should never block do_nothing
            }
        }

        return ToolResult::success([
            'action' => 'do_nothing',
            'reason' => $reason,
            'memory_id' => $memoryId,
            'message' => 'Diagnostische Entscheidung protokolliert: Keine Auffälligkeiten.',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inference', 'diagnostic'],
            'read_only' => false,
            'requires_auth' => false,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

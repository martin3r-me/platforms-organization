<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class RateEnvironmentSourceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment.rate_source';
    }

    public function getDescription(): string
    {
        return 'POST /organization/environment/rate_source - Bewertet eine Umwelt-Quelle nach ihrer Relevanz für die Organisation. Erzeugt oder aktualisiert eine source_relevance Memory.';
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
                'source_name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Umwelt-Quelle.',
                ],
                'relevance_rating' => [
                    'type' => 'number',
                    'description' => 'ERFORDERLICH: Relevanz-Bewertung 0.0-1.0.',
                ],
                'cited_in_signal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wurde ein Signal erzeugt das auf diesen Daten basiert?',
                ],
                'topics_useful' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Themen die relevant waren.',
                ],
                'topics_noise' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Themen die irrelevant/Rauschen waren.',
                ],
                'reasoning' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Begründung der Bewertung.',
                ],
            ],
            'required' => ['source_name', 'relevance_rating', 'reasoning'],
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

            $sourceName = trim((string) ($arguments['source_name'] ?? ''));
            if ($sourceName === '') {
                return ToolResult::error('VALIDATION_ERROR', 'source_name ist erforderlich.');
            }

            $reasoning = trim((string) ($arguments['reasoning'] ?? ''));
            if ($reasoning === '') {
                return ToolResult::error('VALIDATION_ERROR', 'reasoning ist erforderlich.');
            }

            $relevanceRating = max(0.0, min(1.0, (float) ($arguments['relevance_rating'] ?? 0.5)));
            $citedInSignal = (bool) ($arguments['cited_in_signal'] ?? false);
            $topicsUseful = array_slice((array) ($arguments['topics_useful'] ?? []), 0, 20);
            $topicsNoise = array_slice((array) ($arguments['topics_noise'] ?? []), 0, 20);

            // Find source by name + team
            $source = OrganizationEnvironmentSource::forTeam($rootTeamId)
                ->where('name', $sourceName)
                ->first();

            if (! $source) {
                return ToolResult::error('NOT_FOUND', "Quelle '{$sourceName}' nicht gefunden.");
            }

            // Check for existing source_relevance memory
            $existingMemory = OrganizationMemoryEntry::forTeam($rootTeamId)
                ->ofType('source_relevance')
                ->active()
                ->whereJsonContains('structured_data->source_id', $source->id)
                ->first();

            if ($existingMemory) {
                // Update: Exponential Moving Average (70% alt, 30% neu)
                $oldData = $existingMemory->structured_data ?? [];
                $oldRating = (float) ($oldData['relevance_rating'] ?? 0.5);
                $newRating = round($oldRating * 0.7 + $relevanceRating * 0.3, 3);

                // Merge topics
                $mergedUseful = array_values(array_unique(array_merge(
                    (array) ($oldData['topics_useful'] ?? []),
                    $topicsUseful
                )));
                $mergedNoise = array_values(array_unique(array_merge(
                    (array) ($oldData['topics_noise'] ?? []),
                    $topicsNoise
                )));

                $existingMemory->structured_data = [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'relevance_rating' => $newRating,
                    'cited_in_signal' => $citedInSignal || ($oldData['cited_in_signal'] ?? false),
                    'topics_useful' => array_slice($mergedUseful, 0, 30),
                    'topics_noise' => array_slice($mergedNoise, 0, 30),
                    'last_feedback_at' => now()->toIso8601String(),
                ];

                $existingMemory->content = "Source-Relevanz für '{$source->name}': {$newRating} — {$reasoning}";

                // Reinforce or weaken based on rating
                if ($relevanceRating >= 0.5) {
                    $existingMemory->reinforce(0.05, 30);
                } else {
                    $existingMemory->confidence = max(0.0, $existingMemory->confidence - 0.1);
                    $existingMemory->save();
                }

                return ToolResult::success([
                    'id' => $existingMemory->id,
                    'action' => 'updated',
                    'relevance_rating' => $newRating,
                    'confidence' => $existingMemory->confidence,
                    'message' => "Source-Relevanz für '{$source->name}' aktualisiert (EMA: {$newRating}).",
                ]);
            }

            // Create new source_relevance memory
            $entry = OrganizationMemoryEntry::create([
                'team_id' => $rootTeamId,
                'memory_type' => 'source_relevance',
                'content' => "Source-Relevanz für '{$source->name}': {$relevanceRating} — {$reasoning}",
                'structured_data' => [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'relevance_rating' => $relevanceRating,
                    'cited_in_signal' => $citedInSignal,
                    'topics_useful' => $topicsUseful,
                    'topics_noise' => $topicsNoise,
                    'last_feedback_at' => now()->toIso8601String(),
                ],
                'confidence' => 0.5,
                'source_type' => 'inference',
                'valid_until' => now()->addDays(90),
                'is_active' => true,
                'reinforcement_count' => 0,
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'action' => 'created',
                'relevance_rating' => $relevanceRating,
                'confidence' => 0.5,
                'message' => "Source-Relevanz für '{$source->name}' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bewerten der Quelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'environment', 'feedback', 'memory'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

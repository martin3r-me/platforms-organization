<?php

namespace Platform\Organization\Livewire\EnvironmentSnapshot;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationMemoryEntry;

class Show extends Component
{
    public OrganizationEnvironmentSnapshot $snapshot;

    public function mount(OrganizationEnvironmentSnapshot $snapshot)
    {
        $this->snapshot = $snapshot->load('source');
    }

    #[Computed]
    public function sourceRelevanceMemories()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        return OrganizationMemoryEntry::forTeam($teamId)
            ->ofType('source_relevance')
            ->active()
            ->valid()
            ->get()
            ->keyBy(fn ($m) => $m->structured_data['source_id'] ?? null);
    }

    public function getTopicStatus(int $sourceId, string $topic): ?string
    {
        $memory = $this->sourceRelevanceMemories[$sourceId] ?? null;
        if (! $memory) {
            return null;
        }

        $data = $memory->structured_data ?? [];
        if (in_array($topic, $data['topics_useful'] ?? [])) {
            return 'useful';
        }
        if (in_array($topic, $data['topics_noise'] ?? [])) {
            return 'noise';
        }

        return null;
    }

    public function rateTopic(int $sourceId, string $topic, string $rating)
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return;
        }

        // Verify source belongs to team
        $source = OrganizationEnvironmentSource::forTeam($teamId)->find($sourceId);
        if (! $source) {
            return;
        }

        // Find existing source_relevance memory for this source
        $memory = OrganizationMemoryEntry::forTeam($teamId)
            ->ofType('source_relevance')
            ->active()
            ->valid()
            ->get()
            ->first(fn ($m) => ($m->structured_data['source_id'] ?? null) === $sourceId);

        $usefulKey = 'topics_useful';
        $noiseKey = 'topics_noise';
        $oppositeKey = $rating === 'useful' ? $noiseKey : $usefulKey;
        $targetKey = $rating === 'useful' ? $usefulKey : $noiseKey;

        if ($memory) {
            $data = $memory->structured_data ?? [];

            // Remove from opposite list
            $oppositeList = array_values(array_filter(
                $data[$oppositeKey] ?? [],
                fn ($t) => $t !== $topic
            ));

            // Add to target list (deduplicated, max 30)
            $targetList = $data[$targetKey] ?? [];
            if (! in_array($topic, $targetList)) {
                $targetList[] = $topic;
                $targetList = array_slice($targetList, -30);
            }

            $data[$targetKey] = $targetList;
            $data[$oppositeKey] = $oppositeList;
            $data['last_feedback_at'] = now()->toIso8601String();

            $memory->update([
                'structured_data' => $data,
                'confidence' => min(1.0, $memory->confidence + 0.05),
            ]);
        } else {
            // Create new source_relevance memory
            $data = [
                'source_id' => $sourceId,
                'source_name' => $source->name,
                'relevance_rating' => 0.5,
                $targetKey => [$topic],
                $oppositeKey => [],
                'last_feedback_at' => now()->toIso8601String(),
            ];

            OrganizationMemoryEntry::create([
                'team_id' => $teamId,
                'memory_type' => 'source_relevance',
                'key' => "source_relevance_{$sourceId}",
                'content' => "Gelernte Relevanz für Quelle: {$source->name}",
                'structured_data' => $data,
                'confidence' => 0.6,
                'is_active' => true,
                'valid_until' => now()->addMonths(6),
            ]);
        }

        // Reset computed property cache
        unset($this->sourceRelevanceMemories);

        $this->dispatch('toast', message: $rating === 'useful'
            ? "Topic \"{$topic}\" als relevant markiert"
            : "Topic \"{$topic}\" als Rauschen markiert"
        );
    }

    public function render()
    {
        return view('organization::livewire.environment-snapshot.show')
            ->layout('platform::layouts.app');
    }
}

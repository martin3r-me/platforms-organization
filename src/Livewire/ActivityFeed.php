<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\ActivityLog\Models\ActivityLogActivity;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\EntityLinkRegistry;

class ActivityFeed extends Component
{
    public ?int $entityId = null;
    public string $newNote = '';

    public function mount(?int $entityId = null): void
    {
        $this->entityId = $entityId;
    }

    #[Computed]
    public function feedItems()
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if (!$teamId) {
            return collect();
        }

        // Sammle alle activityable-Paare (FQCN => [ids]) die relevant sind
        $activityablePairs = $this->resolveActivityablePairs($teamId);

        if (empty($activityablePairs)) {
            return collect();
        }

        $query = ActivityLogActivity::with('user:id,name,profile_photo_path')
            ->latest()
            ->limit(20);

        // Filter: nur Activities für bekannte (type, id) Paare
        $query->where(function ($q) use ($activityablePairs) {
            foreach ($activityablePairs as $type => $ids) {
                $q->orWhere(function ($sq) use ($type, $ids) {
                    $sq->where('activityable_type', $type);
                    if ($ids !== null) {
                        $sq->whereIn('activityable_id', $ids);
                    }
                });
            }
        });

        return $query->get();
    }

    /**
     * Resolve FQCN => morph key.
     * Wenn FQCN in der Morph-Map steht, kommt der Alias zurück,
     * sonst der FQCN selbst (Laravel-Default für nicht-gemappte Models).
     */
    protected function getMorphKey(string $fqcn): string
    {
        static $fqcnToAlias = null;
        if ($fqcnToAlias === null) {
            $fqcnToAlias = array_flip(Relation::morphMap());
        }

        return $fqcnToAlias[$fqcn] ?? $fqcn;
    }

    protected function resolveActivityablePairs(int $teamId): array
    {
        $pairs = [];
        $morphMap = Relation::morphMap();

        if ($this->entityId) {
            // Entity-spezifisch
            $pairs[$this->getMorphKey(OrganizationEntity::class)][] = $this->entityId;

            // Verknüpfte Models über entity_links (linkable_type IST bereits der Morph-Alias)
            $links = OrganizationEntityLink::where('entity_id', $this->entityId)->get();
            foreach ($links as $link) {
                $fqcn = $morphMap[$link->linkable_type] ?? $link->linkable_type;
                if (class_exists($fqcn)) {
                    $pairs[$link->linkable_type][] = $link->linkable_id;
                }
            }

            // TimeEntries des Teams
            $timeEntryIds = OrganizationTimeEntry::where('team_id', $teamId)->pluck('id')->toArray();
            if (!empty($timeEntryIds)) {
                $key = $this->getMorphKey(OrganizationTimeEntry::class);
                $pairs[$key] = array_merge($pairs[$key] ?? [], $timeEntryIds);
            }
        } else {
            // Dashboard: Team-weit
            $teamEntityIds = OrganizationEntity::where('team_id', $teamId)->pluck('id')->toArray();
            if (!empty($teamEntityIds)) {
                $pairs[$this->getMorphKey(OrganizationEntity::class)] = $teamEntityIds;
            }

            // Alle verknüpften Models über entity_links dieses Teams
            $links = OrganizationEntityLink::whereIn('entity_id', $teamEntityIds)->get();
            foreach ($links as $link) {
                $fqcn = $morphMap[$link->linkable_type] ?? $link->linkable_type;
                if (class_exists($fqcn)) {
                    $pairs[$link->linkable_type][] = $link->linkable_id;
                }
            }

            // TimeEntries des Teams
            $timeEntryIds = OrganizationTimeEntry::where('team_id', $teamId)->pluck('id')->toArray();
            if (!empty($timeEntryIds)) {
                $key = $this->getMorphKey(OrganizationTimeEntry::class);
                $pairs[$key] = array_merge($pairs[$key] ?? [], $timeEntryIds);
            }
        }

        // Cascade: Child-Models über Provider resolven (z.B. Project → Tasks)
        $registry = app(EntityLinkRegistry::class);
        $childPairs = $registry->resolveActivityChildren($pairs);
        foreach ($childPairs as $morphKey => $ids) {
            $pairs[$morphKey] = array_merge($pairs[$morphKey] ?? [], $ids);
        }

        // Deduplizieren
        foreach ($pairs as $type => $ids) {
            if (is_array($ids)) {
                $pairs[$type] = array_values(array_unique($ids));
            }
        }

        return $pairs;
    }

    public function addNote(): void
    {
        if (!$this->entityId) {
            return;
        }

        $this->validate(['newNote' => 'required|string|max:1000']);

        $entity = OrganizationEntity::find($this->entityId);
        if ($entity) {
            $entity->logActivity($this->newNote);
        }

        $this->newNote = '';
        unset($this->feedItems);
    }

    public function deleteNote(int $activityId): void
    {
        ActivityLogActivity::where('id', $activityId)
            ->where('activity_type', 'manual')
            ->where('user_id', auth()->id())
            ->delete();

        unset($this->feedItems);
    }

    public function render()
    {
        return view('organization::livewire.activity-feed');
    }
}

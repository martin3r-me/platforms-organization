<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\ActivityLog\Models\ActivityLogActivity;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationTimeEntry;

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
        $query = ActivityLogActivity::with('user:id,name,profile_photo_path')
            ->latest()
            ->limit(20);

        if ($this->entityId) {
            // Entity-spezifisch: Activities der Entity selbst + ihrer TimeEntries
            $entity = OrganizationEntity::find($this->entityId);
            if (!$entity) {
                return collect();
            }

            // Collect activityable pairs: Entity + all TimeEntries der Entity
            $timeEntryIds = OrganizationTimeEntry::where('team_id', auth()->user()?->currentTeam?->id)
                ->whereHas('context', function ($q) use ($entity) {
                    // This won't work for morph — use raw approach
                })
                ->pluck('id')
                ->toArray();

            // Simpler: alle Activities die auf diese Entity ODER auf TimeEntries zeigen
            $entityClass = OrganizationEntity::class;
            $timeEntryClass = OrganizationTimeEntry::class;

            $query->where(function ($q) use ($entity, $entityClass, $timeEntryClass) {
                // Activities direkt auf der Entity
                $q->where(function ($sq) use ($entity, $entityClass) {
                    $sq->where('activityable_type', $entityClass)
                        ->where('activityable_id', $entity->id);
                });

                // Activities auf TimeEntries die zum Team gehören
                // (Team-Filter passiert unten)
                $q->orWhere(function ($sq) use ($timeEntryClass) {
                    $sq->where('activityable_type', $timeEntryClass);
                });
            });
        } else {
            // Dashboard: alle Activities im Team-Kontext
            $teamId = auth()->user()?->currentTeam?->id;
            if (!$teamId) {
                return collect();
            }

            $entityClass = OrganizationEntity::class;
            $timeEntryClass = OrganizationTimeEntry::class;

            // Entity-Activities: nur Entities dieses Teams
            $teamEntityIds = OrganizationEntity::where('team_id', $teamId)->pluck('id')->toArray();
            // TimeEntry-Activities: nur TimeEntries dieses Teams
            $teamTimeEntryIds = OrganizationTimeEntry::where('team_id', $teamId)->pluck('id')->toArray();

            if (empty($teamEntityIds) && empty($teamTimeEntryIds)) {
                return collect();
            }

            $query->where(function ($q) use ($entityClass, $timeEntryClass, $teamEntityIds, $teamTimeEntryIds) {
                if (!empty($teamEntityIds)) {
                    $q->orWhere(function ($sq) use ($entityClass, $teamEntityIds) {
                        $sq->where('activityable_type', $entityClass)
                            ->whereIn('activityable_id', $teamEntityIds);
                    });
                }
                if (!empty($teamTimeEntryIds)) {
                    $q->orWhere(function ($sq) use ($timeEntryClass, $teamTimeEntryIds) {
                        $sq->where('activityable_type', $timeEntryClass)
                            ->whereIn('activityable_id', $teamTimeEntryIds);
                    });
                }
            });
        }

        return $query->get();
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

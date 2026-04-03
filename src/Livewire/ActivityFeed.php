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

    /** Only query activities for these known model types */
    protected static array $allowedTypes = [
        OrganizationEntity::class,
        OrganizationTimeEntry::class,
    ];

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

        $entityClass = OrganizationEntity::class;
        $timeEntryClass = OrganizationTimeEntry::class;

        // Pre-fetch IDs scoped to this team
        $teamEntityIds = OrganizationEntity::where('team_id', $teamId)->pluck('id')->toArray();
        $teamTimeEntryIds = OrganizationTimeEntry::where('team_id', $teamId)->pluck('id')->toArray();

        $query = ActivityLogActivity::with('user:id,name,profile_photo_path')
            ->whereIn('activityable_type', static::$allowedTypes)
            ->latest()
            ->limit(20);

        if ($this->entityId) {
            $query->where(function ($q) use ($entityClass, $timeEntryClass, $teamTimeEntryIds) {
                // Activities direkt auf der Entity
                $q->where(function ($sq) use ($entityClass) {
                    $sq->where('activityable_type', $entityClass)
                        ->where('activityable_id', $this->entityId);
                });

                // Activities auf TimeEntries dieses Teams
                if (!empty($teamTimeEntryIds)) {
                    $q->orWhere(function ($sq) use ($timeEntryClass, $teamTimeEntryIds) {
                        $sq->where('activityable_type', $timeEntryClass)
                            ->whereIn('activityable_id', $teamTimeEntryIds);
                    });
                }
            });
        } else {
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

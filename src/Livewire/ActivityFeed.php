<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationEntityLink;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Services\EntityLinkRegistry;

class ActivityFeed extends Component
{
    #[Computed]
    public function feedItems(): array
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if (!$teamId) {
            return [];
        }

        $entries = OrganizationTimeEntry::where('team_id', $teamId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $morphMap = Relation::morphMap();
        $reverseMorphMap = array_flip($morphMap);
        $linkTypeConfig = resolve(EntityLinkRegistry::class)->allLinkTypeConfig();

        // Collect context pairs for reverse-lookup to entities
        $contextPairs = [];
        foreach ($entries as $entry) {
            if ($entry->context_type && $entry->context_id) {
                $contextPairs[$entry->context_type][] = $entry->context_id;
            }
        }

        // Find entity links for these context pairs
        $entityLinkMap = []; // "morphAlias:id" => entityName
        if (!empty($contextPairs)) {
            $query = OrganizationEntityLink::query()->with('entity:id,name');

            $query->where(function ($q) use ($contextPairs, $reverseMorphMap) {
                foreach ($contextPairs as $type => $ids) {
                    $morphAlias = $reverseMorphMap[$type] ?? $type;
                    $q->orWhere(function ($sq) use ($morphAlias, $ids) {
                        $sq->where('linkable_type', $morphAlias)
                            ->whereIn('linkable_id', array_unique($ids));
                    });
                }
            });

            foreach ($query->get() as $link) {
                $key = $link->linkable_type . ':' . $link->linkable_id;
                if ($link->entity) {
                    $entityLinkMap[$key] = $link->entity->name;
                }
            }
        }

        $items = [];
        foreach ($entries as $entry) {
            $morphAlias = $reverseMorphMap[$entry->context_type] ?? $entry->context_type;
            $typeLabel = $linkTypeConfig[$morphAlias]['label'] ?? null;

            $entityName = null;
            if ($entry->context_type && $entry->context_id) {
                $key = $morphAlias . ':' . $entry->context_id;
                $entityName = $entityLinkMap[$key] ?? null;
            }

            // Resolve context model name
            $contextName = null;
            if ($entry->context_type && $entry->context_id) {
                $fqcn = $morphMap[$morphAlias] ?? $entry->context_type;
                if (class_exists($fqcn)) {
                    $model = $fqcn::find($entry->context_id);
                    $contextName = $model?->name ?? $model?->title ?? null;
                }
            }

            $items[] = [
                'user_name' => $entry->user?->name ?? 'Unbekannt',
                'minutes' => $entry->minutes,
                'note' => $entry->note,
                'context_name' => $contextName,
                'type_label' => $typeLabel,
                'entity_name' => $entityName,
                'created_at' => $entry->created_at,
                'work_date' => $entry->work_date,
            ];
        }

        return $items;
    }

    public function render()
    {
        return view('organization::livewire.activity-feed');
    }
}

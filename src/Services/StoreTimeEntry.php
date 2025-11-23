<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimeEntryContext;

class StoreTimeEntry
{
    public function __construct(
        protected TimeContextResolver $resolver
    ) {
    }

    /**
     * Erstellt einen neuen Time-Entry mit automatischer Kontext-Kaskade.
     *
     * @param array $data Entry-Daten (team_id, user_id, context_type, context_id, work_date, minutes, etc.)
     * @return OrganizationTimeEntry
     */
    public function store(array $data): OrganizationTimeEntry
    {
        return DB::transaction(function () use ($data) {
            // 1. Time-Entry erstellen
            $entry = OrganizationTimeEntry::create([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'context_type' => $data['context_type'],
                'context_id' => $data['context_id'],
                'work_date' => $data['work_date'],
                'minutes' => $data['minutes'],
                'rate_cents' => $data['rate_cents'] ?? null,
                'amount_cents' => $data['amount_cents'] ?? null,
                'is_billed' => $data['is_billed'] ?? false,
                'metadata' => $data['metadata'] ?? null,
                'note' => $data['note'] ?? null,
                'root_context_type' => null,
                'root_context_id' => null,
            ]);

            // 2. Vorfahren-Kontexte auflösen (vor dem Primärkontext, um zu wissen ob dieser Root ist)
            $ancestors = $this->resolver->resolveAncestors($data['context_type'], $data['context_id']);
            $firstRoot = null;
            
            // Prüfe ob Ancestors vorhanden sind
            $hasAncestors = !empty($ancestors);
            
            // Wenn keine Ancestors vorhanden sind, ist der primäre Kontext selbst der Root (z.B. Project)
            $isPrimaryRoot = !$hasAncestors;

            // 3. Primärkontext anlegen (depth=0, is_primary=true)
            $primaryLabel = $this->resolver->resolveLabel($data['context_type'], $data['context_id']);
            OrganizationTimeEntryContext::updateOrCreate(
                [
                    'time_entry_id' => $entry->id,
                    'context_type' => $data['context_type'],
                    'context_id' => $data['context_id'],
                ],
                [
                    'depth' => 0,
                    'is_primary' => true,
                    'is_root' => $isPrimaryRoot, // Primärer Kontext ist Root, wenn keine Ancestors vorhanden sind
                    'context_label' => $primaryLabel,
                ]
            );

            // 4. Vorfahren-Kontexte auflösen und anlegen

            foreach ($ancestors as $depth => $ancestor) {
                $ancestorDepth = $depth + 1;
                $isRoot = $ancestor['is_root'] ?? false;
                $ancestorLabel = $ancestor['label'] ?? $this->resolver->resolveLabel($ancestor['type'], $ancestor['id']);

                // Ersten Root-Kontext für root_context_type/root_context_id merken
                if ($firstRoot === null && $isRoot) {
                    $firstRoot = [
                        'type' => $ancestor['type'],
                        'id' => $ancestor['id'],
                    ];
                }

                OrganizationTimeEntryContext::updateOrCreate(
                    [
                        'time_entry_id' => $entry->id,
                        'context_type' => $ancestor['type'],
                        'context_id' => $ancestor['id'],
                    ],
                    [
                        'depth' => $ancestorDepth,
                        'is_primary' => false,
                        'is_root' => $isRoot,
                        'context_label' => $ancestorLabel,
                    ]
                );
            }

            // 5. Root-Kontext am Entry setzen
            // Wenn Ancestors vorhanden sind: Erster Root-Kontext aus Ancestors
            // Wenn keine Ancestors vorhanden sind: Primärer Kontext ist selbst der Root (z.B. Project)
            $rootContextType = null;
            $rootContextId = null;
            
            if ($firstRoot) {
                $rootContextType = $firstRoot['type'];
                $rootContextId = $firstRoot['id'];
            } else {
                // Keine Ancestors = primärer Kontext ist selbst der Root (z.B. Project)
                // Bei Projects: context_type/context_id UND root_context_type/root_context_id beide = Project
                $rootContextType = $data['context_type'];
                $rootContextId = $data['context_id'];
            }
            
            // 6. Prüfe ob KeyResult-Bezug vorhanden ist
            $hasKeyResult = $this->checkKeyResultLink($data['context_type'], $data['context_id'], $rootContextType, $rootContextId);
            
            $entry->update([
                'root_context_type' => $rootContextType,
                'root_context_id' => $rootContextId,
                'has_key_result' => $hasKeyResult,
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Prüft ob ein Context oder dessen Root-Context einen KeyResult-Bezug hat
     * 
     * @param string $contextType Primärer Context-Typ (z.B. Task)
     * @param int $contextId Primärer Context-ID
     * @param string|null $rootContextType Root-Context-Typ (z.B. Project)
     * @param int|null $rootContextId Root-Context-ID
     * @return bool
     */
    protected function checkKeyResultLink(string $contextType, int $contextId, ?string $rootContextType, ?int $rootContextId): bool
    {
        // Prüfe ob OKR-Modul verfügbar ist
        if (!class_exists(\Platform\Okr\Models\KeyResultContext::class)) {
            return false;
        }

        // 1. Prüfe Root-Context (z.B. Project) - das ist der häufigste Fall
        if ($rootContextType && $rootContextId) {
            $hasKeyResult = \Platform\Okr\Models\KeyResultContext::where('context_type', $rootContextType)
                ->where('context_id', $rootContextId)
                ->where('is_primary', true)
                ->exists();
            
            if ($hasKeyResult) {
                return true;
            }
        }

        // 2. Prüfe primären Context direkt (falls z.B. Project direkt als Context gesetzt wurde)
        $hasKeyResult = \Platform\Okr\Models\KeyResultContext::where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->where('is_primary', true)
            ->exists();
        
        if ($hasKeyResult) {
            return true;
        }

        // 3. Für Tasks: Prüfe über Project (falls Task ein Project hat)
        if ($contextType === 'Platform\Planner\Models\PlannerTask' || $contextType === \Platform\Planner\Models\PlannerTask::class) {
            $task = \Platform\Planner\Models\PlannerTask::find($contextId);
            if ($task && $task->project_id) {
                $hasKeyResult = \Platform\Okr\Models\KeyResultContext::where('context_type', 'Platform\Planner\Models\PlannerProject')
                    ->where('context_id', $task->project_id)
                    ->where('is_primary', true)
                    ->exists();
                
                if ($hasKeyResult) {
                    return true;
                }
            }
        }

        return false;
    }
}


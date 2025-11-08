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

            // 2. Primärkontext anlegen (depth=0, is_primary=true)
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
                    'is_root' => false,
                    'context_label' => $primaryLabel,
                ]
            );

            // 3. Vorfahren-Kontexte auflösen und anlegen
            $ancestors = $this->resolver->resolveAncestors($data['context_type'], $data['context_id']);
            $firstRoot = null;

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

            // 4. Root-Kontext am Entry setzen
            // Wenn keine Ancestors vorhanden sind (z.B. bei Projects), ist der primäre Kontext selbst der Root
            if ($firstRoot) {
                $entry->update([
                    'root_context_type' => $firstRoot['type'],
                    'root_context_id' => $firstRoot['id'],
                ]);
            } else {
                // Keine Ancestors = primärer Kontext ist selbst der Root (z.B. Project)
                $entry->update([
                    'root_context_type' => $data['context_type'],
                    'root_context_id' => $data['context_id'],
                ]);
            }

            return $entry->fresh();
        });
    }
}


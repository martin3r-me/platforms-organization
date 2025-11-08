<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Models\OrganizationTimePlannedContext;

class StorePlannedTime
{
    public function __construct(
        protected TimeContextResolver $resolver
    ) {
    }

    /**
     * Erstellt einen neuen Planned-Time-Eintrag mit automatischer Kontext-Kaskade.
     *
     * @param array $data Planned-Daten (team_id, user_id, context_type, context_id, planned_minutes, note, is_active)
     * @return OrganizationTimePlanned
     */
    public function store(array $data): OrganizationTimePlanned
    {
        return DB::transaction(function () use ($data) {
            // 1. Planned-Time-Eintrag erstellen
            $planned = OrganizationTimePlanned::create([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'context_type' => $data['context_type'],
                'context_id' => $data['context_id'],
                'planned_minutes' => $data['planned_minutes'],
                'note' => $data['note'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. Primärkontext anlegen (depth=0, is_primary=true)
            $primaryLabel = $this->resolver->resolveLabel($data['context_type'], $data['context_id']);
            OrganizationTimePlannedContext::updateOrCreate(
                [
                    'planned_id' => $planned->id,
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

            foreach ($ancestors as $depth => $ancestor) {
                $ancestorDepth = $depth + 1;
                $isRoot = $ancestor['is_root'] ?? false;
                $ancestorLabel = $ancestor['label'] ?? $this->resolver->resolveLabel($ancestor['type'], $ancestor['id']);

                OrganizationTimePlannedContext::updateOrCreate(
                    [
                        'planned_id' => $planned->id,
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

            return $planned->fresh();
        });
    }
}


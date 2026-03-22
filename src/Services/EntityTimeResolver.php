<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePlanned;

class EntityTimeResolver
{
    /**
     * Sammelt alle (context_type, context_id[]) Paare für eine Entity.
     *
     * Algorithmus:
     * Entity → OrganizationContexts (aktive) → contextable Model
     *   → direkt: (contextable_type, contextable_id)
     *   → include_children_relations folgen → Kind-Models sammeln
     * Optional: Child-Entities rekursiv (parent_entity_id Hierarchie)
     *
     * @return array<string, int[]> Gruppiert nach context_type
     */
    public function resolveContextPairs(OrganizationEntity $entity, bool $includeChildEntities = false): array
    {
        $pairs = [];

        $this->collectPairsForEntity($entity, $pairs);

        if ($includeChildEntities) {
            $this->collectPairsForChildEntities($entity, $pairs);
        }

        return $pairs;
    }

    /**
     * Baut eine Time-Entry Query für alle Zeiten einer Entity.
     */
    public function buildTimeEntryQuery(OrganizationEntity $entity, bool $includeChildEntities = false): Builder
    {
        $pairs = $this->resolveContextPairs($entity, $includeChildEntities);

        return $this->applyContextPairsToQuery(OrganizationTimeEntry::query(), $pairs);
    }

    /**
     * Baut eine Planned-Time Query für alle geplanten Zeiten einer Entity.
     */
    public function buildPlannedTimeQuery(OrganizationEntity $entity, bool $includeChildEntities = false): Builder
    {
        $pairs = $this->resolveContextPairs($entity, $includeChildEntities);

        return $this->applyContextPairsToQuery(OrganizationTimePlanned::query(), $pairs);
    }

    /**
     * Sammelt Context-Paare für eine einzelne Entity (ohne Children).
     */
    protected function collectPairsForEntity(OrganizationEntity $entity, array &$pairs): void
    {
        $contexts = OrganizationContext::query()
            ->where('organization_entity_id', $entity->id)
            ->where('is_active', true)
            ->get();

        foreach ($contexts as $context) {
            $type = $context->contextable_type;
            $id = $context->contextable_id;

            if (! $type || ! $id) {
                continue;
            }

            // Das contextable Model selbst als Context-Paar
            $pairs[$type][] = $id;

            // include_children_relations folgen
            $childRelations = $context->include_children_relations ?? [];
            if (! empty($childRelations)) {
                $this->collectChildRelationPairs($type, $id, $childRelations, $pairs);
            }
        }
    }

    /**
     * Folgt include_children_relations und sammelt Kind-Model Context-Paare.
     *
     * Unterstützt verschachtelte Relationen wie "tasks", "projectSlots.tasks"
     */
    protected function collectChildRelationPairs(string $modelClass, int $modelId, array $relations, array &$pairs): void
    {
        if (! class_exists($modelClass)) {
            return;
        }

        $model = $modelClass::find($modelId);
        if (! $model) {
            return;
        }

        foreach ($relations as $relationPath) {
            $this->resolveRelationPath($model, $relationPath, $pairs);
        }
    }

    /**
     * Löst einen Relation-Pfad auf (z.B. "tasks" oder "projectSlots.tasks").
     */
    protected function resolveRelationPath($model, string $path, array &$pairs): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();

            foreach ($currentModels as $currentModel) {
                if (! method_exists($currentModel, $segment)) {
                    continue;
                }

                $related = $currentModel->{$segment};

                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    $nextModels = $nextModels->merge($related);
                } elseif ($related instanceof \Illuminate\Database\Eloquent\Model) {
                    $nextModels->push($related);
                }
            }

            $currentModels = $nextModels;
        }

        // Die Blatt-Models als Context-Paare sammeln
        foreach ($currentModels as $leafModel) {
            $type = get_class($leafModel);
            $pairs[$type][] = $leafModel->id;
        }
    }

    /**
     * Sammelt Context-Paare für alle Child-Entities (rekursiv).
     */
    protected function collectPairsForChildEntities(OrganizationEntity $entity, array &$pairs): void
    {
        $children = $entity->children()->where('is_active', true)->get();

        foreach ($children as $child) {
            $this->collectPairsForEntity($child, $pairs);
            $this->collectPairsForChildEntities($child, $pairs);
        }
    }

    /**
     * Wendet Context-Paare als WHERE-Bedingungen auf eine Query an.
     *
     * Gruppiert nach type für effiziente IN()-Queries:
     * WHERE (context_type = 'Project' AND context_id IN (1,2,3))
     *    OR (context_type = 'Task' AND context_id IN (4,5,6))
     */
    protected function applyContextPairsToQuery(Builder $query, array $pairs): Builder
    {
        if (empty($pairs)) {
            // Keine Paare → keine Ergebnisse
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($pairs) {
            foreach ($pairs as $type => $ids) {
                $uniqueIds = array_values(array_unique($ids));
                $q->orWhere(function (Builder $sq) use ($type, $uniqueIds) {
                    $sq->where('context_type', $type)
                       ->whereIn('context_id', $uniqueIds);
                });
            }
        });
    }
}

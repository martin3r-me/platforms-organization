<?php

namespace Platform\Organization\Services;

use Platform\Core\Services\EmbeddingService;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMemoryEntry;

/**
 * Domänen-gescopter Top-k-Abruf von Learn-Loop-Lektionen — EINE Quelle der Wahrheit für den
 * Worker-Endpoint (AgentKnowledgeController) UND das Cockpit-Suchfeld (ProfilePanel).
 *
 * Bevorzugt semantische Suche über den core EmbeddingService (Relevanz statt LIKE), fällt aber
 * sauber auf die bestehende LIKE-Filterung zurück, wenn (noch) kein Embedding-Provider
 * konfiguriert ist, kein Embedding zur Lektion existiert oder kein Query-Text vorliegt —
 * der Abruf darf nie brechen.
 */
class AgentKnowledgeSearchService
{
    /** Entity-Type unter dem Lektionen im core-Embedding-Index liegen (siehe AgentKnowledgeController). */
    private const EMBED_ENTITY_TYPE = 'OrganizationMemoryEntry';

    private const LIMIT = 12;

    /**
     * @param string $package Package-/Task-Kontext des Anfragenden (auch als Fallback-Query genutzt).
     * @param string $query Explizite Frage ans Gedächtnis (überstimmt $package als Suchtext).
     */
    public function lessons(OrganizationEntity $entity, string $package = '', string $query = ''): array
    {
        $prefix = $entity->memoryTypePrefix();
        if (! $prefix) {
            return [];
        }

        $teamId = (int) $entity->team_id;
        $package = trim($package);
        $queryText = trim($query) !== '' ? trim($query) : $package;

        $rows = $queryText !== '' ? $this->semantic($teamId, $prefix, $queryText) : null;

        return $rows ?? $this->like($teamId, $prefix, $package);
    }

    /**
     * Vektor-Suche über die Lektions-Embeddings. Domänen-Scoping: der core EmbeddingService
     * filtert nur nach entity_type, NICHT nach memory_type — Domänen-Trennung (dev.* vs
     * backoffice.*) daher zusätzlich per Metadata-Feld nach dem Retrieval.
     *
     * @return array|null null = kein nutzbares Ergebnis (kein Provider, kein Treffer in der
     *                     eigenen Domäne) → Aufrufer fällt auf die LIKE-Filterung zurück.
     */
    private function semantic(int $teamId, string $prefix, string $queryText): ?array
    {
        try {
            $hits = app(EmbeddingService::class)->search(
                $teamId,
                $queryText,
                [self::EMBED_ENTITY_TYPE],
                self::LIMIT,
            );
        } catch (\Throwable $e) {
            return null;
        }

        $ids = [];
        foreach ($hits as $hit) {
            $hitType = (string) data_get($hit, 'metadata.memory_type', '');
            if ((strstr($hitType, '.', true) ?: $hitType) !== $prefix) {
                continue; // andere Domäne — kein Cross-Domain-Leak
            }
            $ids[] = (int) $hit['entity_id'];
        }

        if (count($ids) === 0) {
            return null;
        }

        $entries = OrganizationMemoryEntry::query()
            ->where('team_id', $teamId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->get(['id', 'content', 'structured_data', 'reinforcement_count'])
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $entry = $entries->get($id);
            if ($entry !== null) {
                $rows[] = $this->formatLesson($entry);
            }
        }

        return count($rows) > 0 ? $rows : null;
    }

    /** Bestehende Filterung ohne Embeddings: Domänen-Präfix + Package-Match oder global. */
    private function like(int $teamId, string $prefix, string $package): array
    {
        $rows = OrganizationMemoryEntry::query()
            ->where('team_id', $teamId)
            ->where('memory_type', 'like', $prefix.'.%')
            ->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->when($package !== '', fn ($q) => $q->where(function ($w) use ($package) {
                $w->where('structured_data->package', $package)
                    ->orWhere('structured_data->scope', 'global');
            }))
            ->orderByDesc('reinforcement_count')
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['content', 'structured_data', 'reinforcement_count']);

        return $rows->map(fn ($m) => $this->formatLesson($m))->all();
    }

    private function formatLesson(OrganizationMemoryEntry $m): array
    {
        return [
            'content' => $m->content,
            'package' => data_get($m->structured_data, 'package'),
            'kind' => data_get($m->structured_data, 'kind'),
            'reinforced' => (int) $m->reinforcement_count,
        ];
    }
}

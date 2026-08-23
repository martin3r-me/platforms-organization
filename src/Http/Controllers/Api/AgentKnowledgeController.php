<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Core\Services\EmbeddingService;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMemoryEntry;

/**
 * Domänen-Wissensbasis für Agenten (Learn-Loop). Der Client SCHREIBT nach einem Run eine
 * wiederverwendbare Lektion und ZIEHT beim Claim die relevanten Lektionen für sein Package.
 * Wissen gehört der DOMÄNE (nicht dem einzelnen Agenten): team-weit geteilt (entity_id=null),
 * per memory_type `dev.*` partitioniert — bumblebee und ironhide lernen aus demselben Pool.
 * Speicher = OrganizationMemoryEntry (Dedup über reinforcement_count, Verfall über valid_until).
 * KEINE verdeckte KI: die Plattform speichert/liefert nur; das Destillieren macht der Client.
 */
class AgentKnowledgeController extends Controller
{
    private const TYPE = 'dev.lesson';

    /** Entity-Type unter dem Lektionen im core-Embedding-Index liegen (siehe embedAndStore/search unten). */
    private const EMBED_ENTITY_TYPE = 'OrganizationMemoryEntry';

    /** Ab dieser Cosine-Similarity gilt eine neue Lektion als Dublette einer bestehenden. */
    private const SIMILARITY_THRESHOLD = 0.9;

    /**
     * GET /api/org/agent/knowledge?package=X — relevante Dev-Lektionen (Package + global),
     * gerankt nach Verstärkung/Confidence/Aktualität, gekappt (schmale Prompt-Scheibe).
     */
    public function index(Request $request): JsonResponse
    {
        $entity = $this->agentEntity($request);
        if (! $entity) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        $package = trim((string) $request->query('package', ''));

        $rows = OrganizationMemoryEntry::query()
            ->where('team_id', (int) $entity->team_id)
            ->where('memory_type', 'like', 'dev.%')
            ->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->when($package !== '', fn ($q) => $q->where(function ($w) use ($package) {
                $w->where('structured_data->package', $package)
                    ->orWhere('structured_data->scope', 'global');
            }))
            ->orderByDesc('reinforcement_count')
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['content', 'structured_data', 'reinforcement_count']);

        return response()->json(['data' => $rows->map(fn ($m) => [
            'content' => $m->content,
            'package' => data_get($m->structured_data, 'package'),
            'kind' => data_get($m->structured_data, 'kind'),
            'reinforced' => (int) $m->reinforcement_count,
        ])->all()]);
    }

    /**
     * POST /api/org/agent/knowledge {content, package?, kind?, source_id?} — Lektion ablegen.
     * Dedup zweistufig: 1) exakter Content-Match (Team+Typ+Package), 2) semantische Ähnlichkeit
     * über den core EmbeddingService (Team+Domäne). Beide Treffer → reinforcement_count++ statt
     * Duplikat. Sonst neu anlegen und für künftige Vergleiche embedden (fail-open, s. u.).
     */
    public function store(Request $request): JsonResponse
    {
        $entity = $this->agentEntity($request);
        if (! $entity) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        $data = $request->validate([
            'content' => 'required|string|max:2000',
            'package' => 'nullable|string|max:100',
            'kind' => 'nullable|string|max:30',
            'source_id' => 'nullable|integer',
        ]);

        $teamId = (int) $entity->team_id;
        $content = trim($data['content']);
        $package = $data['package'] ?? null;

        $existing = OrganizationMemoryEntry::query()
            ->where('team_id', $teamId)
            ->where('memory_type', self::TYPE)
            ->when($package !== null, fn ($q) => $q->where('structured_data->package', $package))
            ->where('content', $content)
            ->first();

        $existing ??= $this->findSemanticDuplicate($teamId, $content);

        if ($existing) {
            $existing->increment('reinforcement_count');
            $existing->is_active = true;
            $existing->save();

            return response()->json(['data' => ['id' => $existing->id, 'reinforced' => (int) $existing->reinforcement_count]]);
        }

        $m = OrganizationMemoryEntry::create([
            'team_id' => $teamId,
            'entity_id' => null, // domänen-geteilt (nicht an einen Agenten gebunden)
            'memory_type' => self::TYPE,
            'content' => $content,
            'structured_data' => array_filter([
                'package' => $package,
                'kind' => $data['kind'] ?? null,
                'learned_by' => $entity->id, // Attribution: welcher Agent hat gelernt
            ]),
            'confidence' => 0.7,
            'source_type' => 'dev_issue',
            'source_id' => $data['source_id'] ?? null,
            'is_active' => true,
            'reinforcement_count' => 0,
        ]);

        $this->embedLesson($m);

        return response()->json(['data' => ['id' => $m->id]]);
    }

    /**
     * Semantische Near-Duplicate-Suche vor dem Insert. Domänen-gescoped über den memory_type-
     * Präfix (z. B. "dev" aus "dev.lesson") — eine backoffice-Lektion darf niemals eine dev-
     * Lektion verstärken. Fail-open: kein Embedding-Provider/-Fehler → einfach neu anlegen.
     */
    private function findSemanticDuplicate(int $teamId, string $content): ?OrganizationMemoryEntry
    {
        $domain = strstr(self::TYPE, '.', true) ?: self::TYPE;

        try {
            $hits = app(EmbeddingService::class)->search(
                $teamId,
                $content,
                [self::EMBED_ENTITY_TYPE],
                3,
                self::SIMILARITY_THRESHOLD,
            );
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($hits as $hit) {
            $hitType = (string) data_get($hit, 'metadata.memory_type', '');
            if ((strstr($hitType, '.', true) ?: $hitType) !== $domain) {
                continue; // andere Domäne (z. B. backoffice) — kein Cross-Domain-Merge
            }

            $match = OrganizationMemoryEntry::query()
                ->where('team_id', $teamId)
                ->find((int) ($hit['entity_id'] ?? 0));

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Legt die Lektion im core-Embedding-Index ab, damit künftige ähnliche Lektionen sie als
     * Dublette erkennen. Best-effort: schlägt der EmbeddingService fehl (kein Provider, API-
     * Fehler), bleibt die Lektion trotzdem gespeichert — kein Verlust.
     */
    private function embedLesson(OrganizationMemoryEntry $m): void
    {
        try {
            app(EmbeddingService::class)->embedAndStore(
                teamId: (int) $m->team_id,
                entityType: self::EMBED_ENTITY_TYPE,
                entityId: $m->id,
                text: $m->content,
                metadata: ['memory_type' => $m->memory_type],
            );
        } catch (\Throwable $e) {
            // fail-open: Embedding ist optional, das Ablegen der Lektion darf nicht daran hängen.
        }
    }

    private function agentEntity(Request $request): ?OrganizationEntity
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return null;
        }

        return OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
    }
}

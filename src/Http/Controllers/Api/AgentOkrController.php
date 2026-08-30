<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * FOKUS & ZIELE als Agent-Vertrag, ZWEI EBENEN (wie bei Menschen):
 *   1. PERSÖNLICHES OKR — das OKR, das per dimensionLink an der Agenten-ENTITÄT hängt. Voll (alle
 *      Objectives + KRs); das ist SEINS (Selbstentwicklung).
 *   2. ZUGEWIESENE OBJECTIVES — Objectives in FREMDEN (Venture-)OKRs, deren `user_id` der Bot-User des
 *      Agenten ist. Der Agent bekommt das OKR als KONTEXT (Titel + Venture über den dimensionLink +
 *      Geschwister-Objective-Titel), aber nur SEINE Objectives voll (mit KRs) — „mein Beitrag in diesem
 *      Bild", nicht „das OKR gehört mir". Der Antrieb/Gap speist sich NUR aus den eigenen Objectives.
 *
 * Basis der Venture-Zuordnung ist IMMER der dimensionLink des OKRs (das Okr-Modell hat kein Entity-Feld).
 * Weiche Kopplung zum okr-Modul (class_exists) → leere Ziele statt Fehler.
 */
class AgentOkrController extends Controller
{
    private const CURRENT_CYCLE_STATES = ['active', 'ending_soon'];
    private const OKR_MORPH = 'okr';

    /**
     * GET /api/org/agent/okrs — persönliches OKR (voll) + zugewiesene Objectives (im OKR-Kontext).
     */
    public function okrs(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        $objectiveClass = \Platform\Okr\Models\Objective::class;
        $okrClass = \Platform\Okr\Models\Okr::class;
        if (! class_exists($objectiveClass) || ! class_exists($okrClass)) {
            return response()->json(['data' => ['cycle' => '', 'personal' => [], 'assigned' => []]]);
        }

        $cycleLabel = '';
        try {
            // 1. PERSÖNLICH: OKRs, die per dimensionLink an der Agenten-Entität hängen.
            $personalOkrIds = $this->personalOkrIds((int) $agent->id);
            $personal = $this->objectivesForOkrs($objectiveClass, $personalOkrIds, $cycleLabel);

            // 2. ZUGEWIESEN: Objectives mit user_id = Bot-User in FREMDEN OKRs.
            $assigned = $this->assignedOkrs($objectiveClass, $okrClass, $userId, $personalOkrIds, $cycleLabel);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['cycle' => '', 'personal' => [], 'assigned' => []]]);
        }

        return response()->json(['data' => [
            'cycle' => $cycleLabel,
            'personal' => $personal,
            'assigned' => $assigned,
        ]]);
    }

    /** OKR-IDs, die per dimensionLink an der Agenten-Entität hängen (= sein persönliches OKR). */
    private function personalOkrIds(int $agentEntityId): array
    {
        return EntityDimensionBridge::linksForEntity($agentEntityId)
            ->where('linkable_type', self::OKR_MORPH)
            ->pluck('linkable_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /** Alle Objectives (laufender Zyklus) dieser OKRs → voll (alle sind die des Agenten). */
    private function objectivesForOkrs(string $objectiveClass, array $okrIds, string &$cycleLabel): array
    {
        if (empty($okrIds)) {
            return [];
        }
        $objs = $this->activeObjectives($objectiveClass)->whereIn('okr_id', $okrIds)->orderBy('order')->get();
        $out = [];
        foreach ($objs as $o) {
            $this->captureCycle($o, $cycleLabel);
            $out[] = $this->objectiveArray($o);
        }
        return $out;
    }

    /**
     * Zugewiesene Objectives (user_id = Bot-User) in FREMDEN OKRs, jeweils MIT OKR-Kontext:
     * OKR-Titel + Venture (dimensionLink) + Geschwister-Objective-Titel; die eigenen Objectives voll.
     */
    private function assignedOkrs(string $objectiveClass, string $okrClass, int $botUserId, array $personalOkrIds, string &$cycleLabel): array
    {
        $mine = $this->activeObjectives($objectiveClass)
            ->where('user_id', $botUserId)
            ->when(! empty($personalOkrIds), fn ($q) => $q->whereNotIn('okr_id', $personalOkrIds))
            ->get();
        if ($mine->isEmpty()) {
            return [];
        }

        $okrIds = $mine->pluck('okr_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        // Venture je OKR über den dimensionLink des OKRs.
        $ventureByOkr = [];
        try {
            foreach (EntityDimensionBridge::linksForLinkables([self::OKR_MORPH], $okrIds, true) as $lnk) {
                if ($ent = $lnk->entity) {
                    $ventureByOkr[(int) $lnk->linkable_id] = (string) $ent->name;
                }
            }
        } catch (\Throwable $e) {
            // ohne Venture-Kontext weiter — nicht kritisch
        }

        $okrTitles = $okrClass::query()->whereIn('id', $okrIds)->pluck('title', 'id');

        // Alle Objectives dieser OKRs (laufender Zyklus) für den Geschwister-Kontext.
        $allByOkr = $this->activeObjectives($objectiveClass)->whereIn('okr_id', $okrIds)->orderBy('order')->get()->groupBy('okr_id');

        $out = [];
        foreach ($okrIds as $okrId) {
            $objs = $allByOkr->get($okrId) ?? collect();
            $myObjectives = [];
            $context = [];
            foreach ($objs as $o) {
                $this->captureCycle($o, $cycleLabel);
                if ((int) $o->user_id === $botUserId) {
                    $myObjectives[] = $this->objectiveArray($o);
                } else {
                    $context[] = (string) $o->title;
                }
            }
            $out[] = [
                'okr_title' => (string) ($okrTitles[$okrId] ?? ''),
                'venture' => (string) ($ventureByOkr[$okrId] ?? ''),
                'context_objectives' => $context,
                'my_objectives' => $myObjectives,
            ];
        }
        return $out;
    }

    /** Basis-Query: Objectives im laufenden Zyklus, mit KRs + Cycle geladen. */
    private function activeObjectives(string $objectiveClass)
    {
        return $objectiveClass::query()
            ->whereHas('cycle', fn ($q) => $q->whereIn('status', self::CURRENT_CYCLE_STATES))
            ->with(['keyResults', 'cycle.template']);
    }

    /** Ein Objective als Array: Titel + Beschreibung + KRs (Progress als „X% → 100%" = die Lücke). */
    private function objectiveArray($o): array
    {
        $krs = [];
        foreach ($o->keyResults as $kr) {
            $progress = $kr->performance_score !== null ? (float) $kr->performance_score : 0.0;
            $krs[] = [
                'title' => (string) $kr->title,
                'current' => round($progress * 100),
                'target' => 100,
                'unit' => '%',
                'progress' => $progress,
            ];
        }
        return [
            'title' => (string) $o->title,
            'description' => (string) ($o->description ?? ''),
            'key_results' => $krs,
        ];
    }

    /** Zyklus-Label aus dem ersten gesehenen Objective (Template-Titel, sonst Status). */
    private function captureCycle($o, string &$cycleLabel): void
    {
        if ($cycleLabel !== '') {
            return;
        }
        try {
            $c = $o->cycle;
            if ($c) {
                $cycleLabel = (string) ($c->template?->title ?: $c->status ?: '');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

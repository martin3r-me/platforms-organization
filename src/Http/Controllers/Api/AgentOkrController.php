<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;

/**
 * FOKUS & ZIELE als Agent-Vertrag: der Client-Daemon ZIEHT die aktuellen OKRs des Agenten
 * (`okrs`) und lädt sie als DNA-Achse (immer im Primer, formt Salienz + Effort). Die OKRs
 * gehören dem Bot-User des Agenten (`user_id`), den er später selbst pflegt — hier nur lesen.
 *
 * Weiche Kopplung zum okr-Modul (class_exists): fehlt es, liefert der Endpoint leere Ziele
 * statt zu brechen. Kein Secret fließt; auth:api → Bot-User-Token → agent-Entität.
 */
class AgentOkrController extends Controller
{
    /** Cycle-Status, die als „laufend" gelten (aktueller Fokus). */
    private const CURRENT_CYCLE_STATES = ['active', 'ending_soon'];

    /**
     * GET /api/org/agent/okrs — die OKRs des aktuellen Zyklus, die dem Agenten gehören.
     */
    public function okrs(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        // Nur echte Agent-Tokens: die Entität muss ein Agent sein (Konsistenz mit den anderen Endpoints).
        $isAgent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->exists();
        if (! $isAgent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        return response()->json(['data' => $this->goalsForUser($userId)]);
    }

    /**
     * Baut die Ziel-Struktur (cycle + objectives[ + key_results ]) für den Bot-User.
     * Progress = performance_score (0..1) → als „Ist X% → Soll 100%" dargestellt (die Lücke),
     * universell über alle KR-Typen (metrik-getrieben oder manuell), ohne Measure-Komplexität.
     */
    private function goalsForUser(int $userId): array
    {
        $okrClass = \Platform\Okr\Models\Okr::class;
        if (! class_exists($okrClass)) {
            return ['cycle' => '', 'objectives' => []];
        }

        try {
            /** @var iterable $okrs */
            $okrs = $okrClass::query()
                ->where('user_id', $userId)
                ->where('is_template', false)
                ->with(['cycles' => function ($q) {
                    $q->whereIn('status', self::CURRENT_CYCLE_STATES)
                        ->with(['objectives.keyResults', 'template']);
                }])
                ->get();
        } catch (\Throwable $e) {
            return ['cycle' => '', 'objectives' => []];
        }

        $cycleLabel = '';
        $objectives = [];
        foreach ($okrs as $okr) {
            foreach ($okr->cycles as $cycle) {
                if ($cycleLabel === '') {
                    $cycleLabel = $this->cycleLabel($cycle);
                }
                foreach ($cycle->objectives as $objective) {
                    $krs = [];
                    foreach ($objective->keyResults as $kr) {
                        $progress = $kr->performance_score !== null ? (float) $kr->performance_score : 0.0;
                        $krs[] = [
                            'title' => (string) $kr->title,
                            'current' => round($progress * 100),
                            'target' => 100,
                            'unit' => '%',
                            'progress' => $progress,
                        ];
                    }
                    $objectives[] = [
                        'title' => (string) $objective->title,
                        'description' => (string) ($objective->description ?? ''),
                        'key_results' => $krs,
                    ];
                }
            }
        }

        return ['cycle' => $cycleLabel, 'objectives' => $objectives];
    }

    /** Ein lesbares Zyklus-Label (Template-Titel, sonst Status), defensiv. */
    private function cycleLabel($cycle): string
    {
        try {
            $t = $cycle->template;
            if ($t && ! empty($t->title)) {
                return (string) $t->title;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return (string) ($cycle->status ?? '');
    }
}

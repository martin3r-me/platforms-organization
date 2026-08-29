<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Services\EntityStrategyPresenter;

/**
 * DAS VERINNERLICHTE WELTBILD als Wahrnehmungs-Quelle: der Daemon ZIEHT das Venture-Portfolio
 * (`portfolio`) — je Träger einen Text-Brief (Mission/Vision/Transformation-Map) + Fingerprint —
 * und lässt es im Schlaf in den Neocortex konsolidieren. NICHT „parat halten" (Dump jeden Takt),
 * sondern lesen → verinnerlichen. Meta-Strategie von BHG digital: „wir arbeiten für ALLE Ventures".
 *
 * auth:api → Bot-User-Token → agent-Entität; die Träger im TEAM des Agenten sind sein Portfolio.
 */
class AgentPortfolioController extends Controller
{
    /**
     * GET /api/org/agent/portfolio — Meta-Satz + je Venture ein Strategie-Brief mit Fingerprint.
     */
    public function portfolio(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        // Die Carrier-Entities (Ventures/Träger) im Team des Agenten = sein Portfolio.
        $carriers = OrganizationEntity::query()
            ->where('team_id', $agent->team_id)
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->orderBy('name')
            ->get();

        $ventures = [];
        foreach ($carriers as $carrier) {
            $brief = $this->briefFor($carrier);
            if ($brief === '') {
                continue; // Träger ohne Strategie-Inhalt → nichts zu verinnerlichen
            }
            $ventures[] = [
                'name' => (string) $carrier->name,
                'code' => (string) ($carrier->code ?: $carrier->id),
                'brief' => $brief,
                'fingerprint' => md5($brief),
            ];
        }

        return response()->json(['data' => [
            'meta_strategy' => 'Du bist Mitglied von BHG digital und wirkst für ALLE Ventures des Portfolios, nicht für eines allein.',
            'ventures' => $ventures,
        ]]);
    }

    /**
     * briefFor — flacht die strukturierte Entity-Strategie zu einem lesbaren Text-Brief ab (die Lese-
     * Quelle fürs Verinnerlichen). Leer, wenn der Träger keine Strategie trägt.
     */
    private function briefFor(OrganizationEntity $carrier): string
    {
        try {
            $s = EntityStrategyPresenter::forEntity($carrier);
        } catch (\Throwable $e) {
            return '';
        }
        if (! is_array($s)) {
            return '';
        }

        // Verinnerlichung braucht die ESSENZ (Weltbild), nicht jede Zeile — daher gekappt: Mission/Vision
        // getrimmt, Fokusräume + Meilensteine begrenzt. Der Agent lernt den Geist, nicht das Auswendige.
        $trim = fn ($v, $n) => \Illuminate\Support\Str::limit(trim((string) $v), $n);

        $lines = [];
        if (! empty($s['mission']['content'])) {
            $lines[] = 'Mission: ' . $trim($s['mission']['content'], 600);
        }
        if (! empty($s['vision']['content'])) {
            $lines[] = 'Vision: ' . $trim($s['vision']['content'], 600);
        }

        $focusAreas = $s['focus_areas'] ?? [];
        if (is_array($focusAreas) && ! empty($focusAreas)) {
            $lines[] = 'Transformation (Fokusräume × Meilensteine):';
            foreach (array_slice($focusAreas, 0, 12) as $fa) {
                $title = trim((string) ($fa['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $desc = trim((string) ($fa['description'] ?? ''));
                $lines[] = '- Fokusraum "' . $title . '"' . ($desc !== '' ? ': ' . $trim($desc, 200) : '');

                $visions = [];
                foreach (array_slice($fa['vision_images'] ?? [], 0, 5) as $vi) {
                    if (! empty($vi['title'])) {
                        $visions[] = trim((string) $vi['title']);
                    }
                }
                if (! empty($visions)) {
                    $lines[] = '  Zielbilder: ' . implode('; ', $visions);
                }

                $miles = [];
                foreach (array_slice($fa['milestones'] ?? [], 0, 8) as $m) {
                    if (empty($m['title'])) {
                        continue;
                    }
                    $when = '';
                    if (! empty($m['target_year'])) {
                        $when = ' (' . (! empty($m['target_quarter']) ? 'Q' . (int) $m['target_quarter'] . '/' : '') . (int) $m['target_year'] . ')';
                    }
                    $miles[] = trim((string) $m['title']) . $when;
                }
                if (! empty($miles)) {
                    $lines[] = '  Meilensteine: ' . implode('; ', $miles);
                }
            }
        }

        // Harte Obergrenze als letzter Schutz (der Studien-Prompt bleibt bezahlbar).
        return $trim(implode("\n", $lines), 4000);
    }
}

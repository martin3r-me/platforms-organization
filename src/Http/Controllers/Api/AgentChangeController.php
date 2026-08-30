<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Platform\Organization\Models\OrganizationEntity;

/**
 * DIE RICHTUNG als Agent-Vertrag: der Daemon ZIEHT die aktiven Transformationen der Org (`changes`) —
 * was die Org WIRD (nicht was sie IST). Je Vorhaben eine kurze Richtung (fürs Primer) + ein Brief
 * (Beschreibung + aktuelle Phasen) zum Verinnerlichen. BEWUSST OHNE die granularen Maßnahmen/Personen-
 * Aktionen: der Agent lernt die Richtung, nicht den internen Maßnahmenplan.
 *
 * Weiche Kopplung zum change-Modul (class_exists) → leer statt Fehler. auth:api → agent-Entität.
 */
class AgentChangeController extends Controller
{
    /**
     * GET /api/org/agent/changes — die aktiven Change-Vorhaben im Team des Agenten.
     */
    public function changes(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        return response()->json(['data' => ['items' => $this->itemsForTeam((int) $agent->team_id)]]);
    }

    private function itemsForTeam(int $teamId): array
    {
        $projectClass = \Platform\Change\Models\ChangeProject::class;
        if (! class_exists($projectClass)) {
            return [];
        }

        try {
            $projects = $projectClass::query()
                ->where('team_id', $teamId)
                ->where('status', 'active')
                ->with(['phases' => fn ($q) => $q->orderBy('phase_number')])
                ->orderBy('code')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $items = [];
        foreach ($projects as $p) {
            $name = trim((string) $p->name);
            $desc = trim((string) ($p->description ?? ''));
            if ($name === '') {
                continue;
            }
            $brief = $this->briefFor($name, $desc, $p->phases ?? []);
            $items[] = [
                'name' => $name,
                'code' => (string) ($p->code ?: $p->id),
                'direction' => $this->essence($desc),
                'brief' => $brief,
                'fingerprint' => md5($brief),
            ];
        }

        return $items;
    }

    /** essence — der eine Richtungs-Satz fürs Primer (erster Satz, sonst gekappt). */
    private function essence(string $desc): string
    {
        if ($desc === '') {
            return '';
        }
        $firstSentence = preg_split('/(?<=[.!?])\s+/', $desc, 2)[0] ?? $desc;
        return Str::limit(trim($firstSentence), 120);
    }

    /**
     * briefFor — die Lese-Quelle zum Verinnerlichen: Name + Beschreibung + wo die Transformation gerade
     * steht (laufende Phasen mit Kotter-Label + Notiz). OHNE Actions/Personen. Hart gekappt.
     */
    private function briefFor(string $name, string $desc, $phases): string
    {
        $lines = [$name];
        if ($desc !== '') {
            $lines[] = Str::limit($desc, 700);
        }

        $running = [];
        foreach ($phases as $ph) {
            if ((string) ($ph->status?->value ?? $ph->status) !== 'in_progress') {
                continue;
            }
            $label = '';
            try {
                $label = (string) ($ph->phase_number?->label() ?? '');
            } catch (\Throwable $e) {
                // ignore
            }
            $note = trim((string) ($ph->notes ?? ''));
            $line = $label !== '' ? $label : 'laufende Phase';
            if ($note !== '') {
                $line .= ': ' . Str::limit($note, 300);
            }
            $running[] = $line;
        }
        if (! empty($running)) {
            $lines[] = 'Aktueller Stand der Transformation:';
            foreach (array_slice($running, 0, 4) as $r) {
                $lines[] = '- ' . $r;
            }
        }

        return Str::limit(trim(implode("\n", $lines)), 3000);
    }
}

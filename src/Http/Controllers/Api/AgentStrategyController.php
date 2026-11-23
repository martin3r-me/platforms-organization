<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Platform\Core\Authz\AuthzResolver;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\ObjectTypeRegistry;
use Platform\Organization\Strategy\StrategySources;

/**
 * Agent-API für die Ausrichtung auf die Strategie (/api/org/agent/…):
 *   - welche Meilensteine liegen in meiner Sichtweite (fällig im laufenden + nächsten Quartal;
 *     mit past=N auch noch offene aus vergangenen Quartalen, mit scope=mine nur mein Bereich)?
 *   - mein Objekt (z. B. Objective/KR) zahlt auf einen Meilenstein ein
 *   - mein Objekt (z. B. mein persönliches OKR) an meiner Entity verorten
 *
 * LOSE: organization kennt keine fremden Typen. Typen über die ObjectTypeRegistry, Eigentum
 * generisch über AuthzResolver::owns() (Ersteller-Spalte per Konvention) — ein Agent kann nur
 * eigene Objekte verknüpfen.
 */
class AgentStrategyController extends Controller
{
    /**
     * GET /api/org/agent/milestones?quarters=2 — Meilensteine mit Zieltermin im laufenden Quartal
     * und den folgenden (quarters-1). v1: ganze Org (Agenten-Rollen hängen an der Wurzel);
     * die Eingrenzung über die Autorisierung (reachableEntityIds) folgt mit dem Graph-Modus.
     */
    public function milestones(Request $request): JsonResponse
    {
        [$user, $agent, $err] = $this->actor($request);
        if ($err) {
            return $err;
        }
        $quarters = max(1, min(8, (int) $request->query('quarters', 2)));
        $past = max(0, min(8, (int) $request->query('past', 0)));

        $slots = [];
        $d = Carbon::today()->startOfQuarter()->subQuarters($past);
        for ($i = 0; $i < $past + $quarters; $i++) {
            $slots[] = [$d->year, $d->quarter];
            $d = $d->copy()->addQuarter();
        }
        $current = Carbon::today()->startOfQuarter();

        // Quelle: Meilensteine der Management Books des Teams (StrategySources); ohne Quelle leer.
        $rows = array_values(StrategySources::milestonesForTeam((int) $agent->team_id, $slots));
        if ($past > 0) {
            // Vergangene Quartale nur, solange der Meilenstein noch offen ist (überfällig).
            $rows = array_values(array_filter($rows, fn ($m) => ! $this->beforeQuarter($m, $current)
                || ! in_array($m['status'] ?? null, ['reached', 'missed', 'cancelled'], true)));
        }
        // scope=mine: nur Meilensteine der Einheiten, die ich verantworte (und darunter).
        $domain = null;
        if ($request->query('scope') === 'mine') {
            $domain = $this->domainOf($agent);
            $rows = array_values(array_filter($rows, fn ($m) => isset($m['entity_id']) && in_array((int) $m['entity_id'], $domain, true)));
        }
        foreach ($rows as &$m) {
            $m['overdue'] = $this->beforeQuarter($m, $current);
        }
        unset($m);

        return response()->json(['data' => array_filter([
            'quarters' => array_map(fn ($s) => $s[0] . '-Q' . $s[1], $slots),
            'domain' => $domain,
            'milestones' => $rows,
        ], fn ($v) => $v !== null)]);
    }

    /** @param array<string,mixed> $m */
    private function beforeQuarter(array $m, Carbon $quarterStart): bool
    {
        $y = (int) ($m['target_year'] ?? 0);
        $q = (int) ($m['target_quarter'] ?? 0);

        return $y > 0 && $q > 0 && ($y < $quarterStart->year || ($y === $quarterStart->year && $q < $quarterStart->quarter));
    }

    /**
     * Mein Verantwortungsbereich: Einheiten, auf die ich über eine Beziehung mit Gestaltungsrecht
     * (Capability manage/write, z. B. Venture Owner, Führt an, Ist aktiv in) zeige, plus die Einheit
     * meiner Stelle (people) — jeweils mit allen Einheiten darunter.
     *
     * @return list<int>
     */
    private function domainOf(OrganizationEntity $agent): array
    {
        $roots = OrganizationEntityRelationship::query()->active()
            ->where('from_entity_id', $agent->id)
            ->whereHas('relationType', fn ($q) => $q->where(fn ($w) => $w
                ->whereJsonContains('capabilities', 'manage')->orWhereJsonContains('capabilities', 'write')))
            ->pluck('to_entity_id')->map(fn ($id) => (int) $id)->all();
        $held = \Platform\People\Support\HeldJobProfile::class;
        if (class_exists($held)) {
            try {
                $unit = $held::forEntity((int) $agent->id)?->unit_entity_id;
                if ($unit) {
                    $roots[] = (int) $unit;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $all = array_values(array_unique($roots));
        $frontier = $all;
        for ($depth = 0; $frontier !== [] && $depth < 12; $depth++) {
            $frontier = OrganizationEntity::query()->whereIn('parent_entity_id', $frontier)
                ->pluck('id')->map(fn ($id) => (int) $id)->diff($all)->values()->all();
            $all = array_merge($all, $frontier);
        }

        return $all;
    }

    /**
     * POST /api/org/agent/contributions — mein Objekt zahlt auf einen Meilenstein ein.
     * Body: { milestone_id, linkable_type, linkable_id, weight? }  (idempotent)
     */
    public function contribute(Request $request): JsonResponse
    {
        [$user, $agent, $err] = $this->actor($request);
        if ($err) {
            return $err;
        }
        $data = $request->validate([
            'milestone_id' => 'required|integer',
            'linkable_type' => 'required|string|max:120',
            'linkable_id' => 'required|integer',
            'weight' => 'nullable|integer|min:1|max:100',
        ]);

        [$alias, $own] = $this->ownedObject($user, $data['linkable_type'], (int) $data['linkable_id']);
        if ($own) {
            return $own;
        }
        try {
            $result = StrategySources::contribute(
                (int) $agent->team_id,
                (int) $data['milestone_id'],
                $alias,
                (int) $data['linkable_id'],
                array_key_exists('weight', $data) ? $data['weight'] : null,
                (int) $user->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if ($result === null) {
            return response()->json(['message' => "Meilenstein #{$data['milestone_id']} nicht gefunden."], 404);
        }

        return response()->json(
            ['data' => array_merge($result['alignment'], ['created' => $result['created']])],
            $result['created'] ? 201 : 200
        );
    }

    /**
     * POST /api/org/agent/self-link — mein Objekt an MEINER Entity verorten (Dimension-Link), z. B.
     * mein persönliches OKR. Body: { object_type, object_id }  (idempotent)
     */
    public function selfLink(Request $request): JsonResponse
    {
        [$user, $agent, $err] = $this->actor($request);
        if ($err) {
            return $err;
        }
        $data = $request->validate([
            'object_type' => 'required|string|max:120',
            'object_id' => 'required|integer',
        ]);
        [$alias, $own] = $this->ownedObject($user, $data['object_type'], (int) $data['object_id']);
        if ($own) {
            return $own;
        }
        $already = EntityDimensionBridge::linksForEntity((int) $agent->id)
            ->where('linkable_type', $alias)->where('linkable_id', (int) $data['object_id'])->isNotEmpty();
        if (!$already) {
            EntityDimensionBridge::createLink((int) $agent->id, $alias, (int) $data['object_id']);
        }

        return response()->json(['data' => [
            'entity_id' => $agent->id,
            'object_type' => $alias,
            'object_id' => (int) $data['object_id'],
            'created' => !$already,
        ]], $already ? 200 : 201);
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────────────────────

    /** @return array{0:mixed,1:?OrganizationEntity,2:?JsonResponse} */
    private function actor(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [null, null, response()->json(['message' => 'Unauthenticated'], 401)];
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $user->id)->first();
        if (!$agent) {
            return [null, null, response()->json(['message' => 'No agent for this token'], 404)];
        }
        return [$user, $agent, null];
    }

    /**
     * Typ normalisieren und Eigentum prüfen (generisch, ohne Typ-Wissen).
     *
     * @return array{0:?string,1:?JsonResponse}
     */
    private function ownedObject($user, string $type, int $id): array
    {
        $alias = ObjectTypeRegistry::resolve($type);
        $class = $alias ? ObjectTypeRegistry::modelClass($alias) : null;
        if (!$class) {
            return [null, response()->json(['message' => "Objekttyp '{$type}' ist nicht registriert."], 422)];
        }
        if (!app(AuthzResolver::class)->owns($user, $class, $id)) {
            return [null, response()->json(['message' => "{$alias} #{$id} nicht gefunden oder nicht deins."], 403)];
        }
        return [$alias, null];
    }
}

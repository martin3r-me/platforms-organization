<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Platform\Core\Authz\AuthzResolver;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationMilestone;
use Platform\Organization\Models\OrganizationMilestoneContribution;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\ObjectTypeRegistry;

/**
 * Agent-API für die Ausrichtung auf die Strategie (/api/org/agent/…):
 *   - welche Meilensteine liegen in meiner Sichtweite (fällig im laufenden + nächsten Quartal)?
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

        $slots = [];
        $d = Carbon::today()->startOfQuarter();
        for ($i = 0; $i < $quarters; $i++) {
            $slots[] = [$d->year, $d->quarter];
            $d = $d->copy()->addQuarter();
        }

        $rows = OrganizationMilestone::query()
            ->where('team_id', $agent->team_id)
            ->where(function ($q) use ($slots) {
                foreach ($slots as [$y, $qu]) {
                    $q->orWhere(fn ($w) => $w->where('target_year', $y)->where('target_quarter', $qu));
                }
            })
            ->with(['entity:id,name', 'focusArea:id,title'])
            ->orderBy('target_year')->orderBy('target_quarter')->orderBy('entity_id')->orderBy('order')
            ->get();

        return response()->json(['data' => [
            'quarters' => array_map(fn ($s) => $s[0] . '-Q' . $s[1], $slots),
            'milestones' => $rows->map(fn (OrganizationMilestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'central_question' => $m->central_question,
                'entity_id' => $m->entity_id,
                'entity' => $m->entity?->name,
                'focus_area' => $m->focusArea?->title,
                'target_year' => $m->target_year,
                'target_quarter' => $m->target_quarter,
            ])->values()->all(),
        ]]);
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
        $milestone = OrganizationMilestone::query()->where('team_id', $agent->team_id)->find($data['milestone_id']);
        if (!$milestone) {
            return response()->json(['message' => "Meilenstein #{$data['milestone_id']} nicht gefunden."], 404);
        }

        try {
            $c = OrganizationMilestoneContribution::firstOrNew([
                'milestone_id' => $milestone->id,
                'linkable_type' => $alias,
                'linkable_id' => (int) $data['linkable_id'],
            ]);
            $created = !$c->exists;
            if (array_key_exists('weight', $data)) {
                $c->weight = $data['weight'];
            }
            if ($created) {
                $c->team_id = $milestone->team_id;
                $c->created_by_user_id = $user->id;
            }
            $c->save();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $c->load('milestone.focusArea', 'milestone.entity');

        return response()->json(['data' => array_merge($c->toAlignmentArray(), ['created' => $created])], $created ? 201 : 200);
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

<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Support\AgentDomain;
use Platform\Organization\Support\AgentFieldSources;

/**
 * ARBEITSFELD als Agent-Vertrag: meine Einheiten und die Arbeitsobjekte darin (Projekte, Aufgaben …),
 * von den Modulen geliefert. Der Daemon holt es jeden Takt — Arbeit hängt immer an einem Objekt, und ein
 * neues Objekt entsteht nur hier: im Bereich des Agenten und nie doppelt.
 */
class AgentFieldController extends Controller
{
    /**
     * GET /api/org/agent/field
     */
    public function field(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        return response()->json(['data' => AgentFieldSources::forAgent($agent) + ['fetched_at' => now()->toIso8601String()]]);
    }

    /**
     * POST /api/org/agent/field — ein Arbeitsobjekt anlegen (oder das bestehende bekommen).
     * Body: { type, title, parent: {type, id}, description?, due_date?, story_points? }
     * 201 = neu angelegt · 200 + existing=true = dieselbe Arbeit gab es schon offen
     */
    public function create(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        $data = $request->validate([
            'type' => 'required|string|max:64',
            'title' => 'required|string|max:255',
            'parent' => 'required|array',
            'parent.type' => 'required|string|max:64',
            'parent.id' => 'required|integer|min:1',
            'description' => 'nullable|string|max:10000',
            'due_date' => 'nullable|date',
            'story_points' => 'nullable|string|max:8',
        ]);

        $source = AgentFieldSources::creatorOf($data['type']);
        if (! $source) {
            return response()->json(['message' => "Objektart '{$data['type']}' kann hier nicht angelegt werden."], 422);
        }
        $parentType = $source->creatable()[$data['type']];
        if ($data['parent']['type'] !== $parentType) {
            return response()->json(['message' => "'{$data['type']}' hängt an '{$parentType}', nicht an '{$data['parent']['type']}'."], 422);
        }
        // Wurzel-Objekte hängen an einer Einheit — nur in meinem Bereich.
        if ($parentType === 'entity') {
            $domain = AgentDomain::of($agent);
            if (! $domain->contains((int) $data['parent']['id'])) {
                return response()->json(['message' => 'Diese Einheit liegt nicht in deinem Bereich.', 'units' => $domain->units()], 403);
            }
        }

        try {
            $result = $source->create($agent, $data['type'], $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], $result['existing'] ? 200 : 201);
    }

    private function agent(Request $request): ?OrganizationEntity
    {
        $userId = (int) $request->user()?->id;

        return $userId > 0 ? OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first() : null;
    }
}

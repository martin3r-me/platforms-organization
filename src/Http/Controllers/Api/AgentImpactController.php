<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Contracts\AgentImpactProvider;
use Platform\Organization\Models\OrganizationEntity;

/**
 * WIRKUNG als Agent-Vertrag: das Engram liest seinen eigenen Wirkungs-Score (GET /impact) — dieselbe Zahl,
 * die die Organisation sieht. Wie es sie verbessert, sagt ihm niemand.
 *
 * Gerechnet wird beim gebundenen AgentImpactProvider (people); ohne Bindung 503.
 */
class AgentImpactController extends Controller
{
    /** GET /api/org/agent/impact */
    public function impact(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        $agent = $userId > 0 ? OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first() : null;
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        if (! app()->bound(AgentImpactProvider::class)) {
            return response()->json(['message' => 'Agent impact unavailable'], 503);
        }

        $impact = app(AgentImpactProvider::class)->impactFor((int) $agent->id);
        if ($impact === null) {
            return response()->json(['message' => 'Agent is no employee'], 404);
        }

        return response()->json(['data' => $impact]);
    }
}

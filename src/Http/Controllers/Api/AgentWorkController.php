<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Support\AgentWorkSources;

/**
 * ARBEITSEINGANG als Agent-Vertrag: was Module dem Agenten hingelegt haben (Zustell-Aufträge, Entwürfe …),
 * je Art eine Zeile mit Anzahl, Dringlichkeit und dem Tool, mit dem er sie abarbeitet. Der Daemon holt das
 * jeden Takt und faltet es in den Fingerabdruck des Change-Gates – liegt etwas Neues, wird gedacht.
 */
class AgentWorkController extends Controller
{
    /**
     * GET /api/org/agent/work
     */
    public function work(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        $agent = $userId > 0 ? OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first() : null;
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        return response()->json(['data' => AgentWorkSources::forAgent($agent) + ['fetched_at' => now()->toIso8601String()]]);
    }
}

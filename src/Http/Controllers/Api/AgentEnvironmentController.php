<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EnvironmentMovementService;

/**
 * UMWELT als Agent-Vertrag: der Sensor-Feld-Sinn für S4 (Perceptor) — das Außen (Markt/Wettbewerb/
 * Makro/Feeds) mit BEWEGUNG (Deltas = Signal-Rohstoff). Nutzt den bestehenden EnvironmentMovementService
 * (`buildInferenceContext`), der je aktive Quelle den letzten Stand + Delta liefert und Irrelevantes
 * (gelernte Relevanz) filtert. Rollen-Gating passiert im Daemon (nur hasVsm("S4") fetcht).
 */
class AgentEnvironmentController extends Controller
{
    /**
     * GET /api/org/agent/environment — Umwelt-Quellen mit Bewegung (owner-relevant, gefiltert).
     */
    public function environment(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'No user for this token'], 404);
        }
        $agent = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        $sources = [];
        try {
            $sources = app(EnvironmentMovementService::class)->buildInferenceContext((int) $agent->team_id);
        } catch (\Throwable $e) {
            $sources = [];
        }

        return response()->json(['data' => ['sources' => array_values($sources)]]);
    }
}

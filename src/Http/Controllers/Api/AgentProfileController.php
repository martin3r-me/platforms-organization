<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationAgentProfile;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Agent-Vertrag: der Client-Daemon ZIEHT seine Config (`profile`) und MELDET Status
 * (`heartbeat`) — authentifiziert per Bot-User-Token (auth:api → linked_user_id → agent-Entity
 * → Profil). KEINE Secrets fließen hier; der Claude-Login + GitHub-Token liegen auf dem Client.
 */
class AgentProfileController extends Controller
{
    /**
     * GET /api/org/agent/profile — die Runtime-Config des Agenten.
     */
    public function profile(Request $request): JsonResponse
    {
        $profile = $this->profileForUser($request);
        if (! $profile) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        return response()->json(['data' => [
            'domain' => $profile->domain,
            'stages' => $profile->stages ?? [],
            'active' => (bool) $profile->active,
            'governor' => [
                'five_hour_reserve_pct' => $profile->five_hour_reserve_pct,
                'seven_day_burn_margin_pct' => $profile->seven_day_burn_margin_pct,
            ],
            'github_username' => $profile->github_username,
        ]]);
    }

    /**
     * POST /api/org/agent/heartbeat — Status/Usage, den der Daemon zurückmeldet.
     * Antwort trägt `active` (an/aus) zurück, damit der Daemon sofort weiß, ob er pausieren soll.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $profile = $this->profileForUser($request);
        if (! $profile) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        $data = $request->validate([
            'status' => 'nullable|string|max:32',
            'claude_subscription' => 'nullable|string|max:64',
            'five_hour_pct' => 'nullable|numeric|min:0|max:100',
            'seven_day_pct' => 'nullable|numeric|min:0|max:100',
            'github_username' => 'nullable|string|max:255',
        ]);

        // Nur gemeldete Felder überschreiben; Heartbeat-Zeit immer setzen.
        $profile->fill(array_filter($data, fn ($v) => $v !== null));
        $profile->last_heartbeat_at = now();
        $profile->save();

        return response()->json(['data' => ['ok' => true, 'active' => (bool) $profile->active]]);
    }

    private function profileForUser(Request $request): ?OrganizationAgentProfile
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return null;
        }
        $entity = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();

        return $entity?->agentProfile;
    }
}

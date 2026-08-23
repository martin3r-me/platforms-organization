<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationAgentProfile;
use Platform\Organization\Models\OrganizationAgentRunEvent;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Models\OrganizationTimeEntry;

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

        // Identität (Domäne × Stufen) aus den Rollen-Assignments des Agenten ableiten — nicht
        // mehr aus dem Profil. Invariante „eine Domäne pro Agent": die erste nicht-leere zählt.
        $roles = OrganizationRoleAssignment::query()
            ->where('person_entity_id', $profile->organization_entity_id)
            ->with('role')
            ->get()
            ->pluck('role')
            ->filter();

        $domain = $roles->pluck('domain')->filter()->unique()->first();
        $stages = $roles->pluck('stage')->filter()->unique()->values()->all();

        return response()->json(['data' => [
            'domain' => $domain,
            'stages' => $stages,
            'active' => (bool) $profile->active,
            'governor' => [
                'five_hour_reserve_pct' => $profile->five_hour_reserve_pct,
                'seven_day_burn_margin_pct' => $profile->seven_day_burn_margin_pct,
            ],
            'max_story_points' => $profile->max_story_points,
            'claude_model' => $profile->claude_model,
            'claim_unassigned' => (bool) $profile->claim_unassigned,
            'github_username' => $profile->github_username,
            'settings' => $profile->settings ?? [],
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

    /**
     * POST /api/org/agent/log — der Daemon meldet seinen Aktivitäts-Feed (Claim, Reads/Edits,
     * Shell, Git-Schritte, Ergebnis). Kuratiert, kein Voll-Token-Strom. Gepruned auf die
     * jüngsten Events pro Agent, damit die Tabelle nicht unbegrenzt wächst.
     */
    public function log(Request $request): JsonResponse
    {
        $profile = $this->profileForUser($request);
        if (! $profile) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        $data = $request->validate([
            'run_id' => 'required|string|max:64',
            'events' => 'required|array|max:200',
            'events.*.kind' => 'required|string|max:24',
            'events.*.text' => 'nullable|string|max:2000',
        ]);

        $entityId = (int) $profile->organization_entity_id;
        $now = now();
        $rows = array_map(fn ($e) => [
            'organization_entity_id' => $entityId,
            'run_id' => $data['run_id'],
            'kind' => $e['kind'],
            'text' => $e['text'] ?? null,
            'created_at' => $now,
        ], $data['events']);

        OrganizationAgentRunEvent::insert($rows);

        // Pruning: nur die jüngsten ~2000 Events pro Agent behalten.
        $cutoff = OrganizationAgentRunEvent::where('organization_entity_id', $entityId)
            ->orderByDesc('id')
            ->skip(2000)
            ->take(1)
            ->value('id');
        if ($cutoff) {
            OrganizationAgentRunEvent::where('organization_entity_id', $entityId)
                ->where('id', '<=', $cutoff)
                ->delete();
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * GET /api/org/agent/events — jüngste Run-Events des Agenten (Observability). Optional
     * gefiltert nach kind (z. B. „fail" für Ablehnungsgründe) oder run_id. Neueste zuerst.
     */
    public function events(Request $request): JsonResponse
    {
        $profile = $this->profileForUser($request);
        if (! $profile) {
            return response()->json(['message' => 'No agent profile for this token'], 404);
        }

        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'kind' => 'nullable|string|max:24',
            'run_id' => 'nullable|string|max:64',
        ]);

        $query = OrganizationAgentRunEvent::where('organization_entity_id', (int) $profile->organization_entity_id)
            ->orderByDesc('id');
        if (! empty($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (! empty($data['run_id'])) {
            $query->where('run_id', $data['run_id']);
        }

        $events = $query->limit((int) ($data['limit'] ?? 100))
            ->get(['id', 'run_id', 'kind', 'text', 'created_at'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'run_id' => $e->run_id,
                'kind' => $e->kind,
                'text' => $e->text,
                'at' => (string) $e->created_at,
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * GET /api/org/agent/stats — getrackte Zeit des Agenten (24 h / laufender Monat) fürs
     * Dashboard. Quelle: OrganizationTimeEntry (vom Agenten via org/agent/time gestempelt).
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $sum = fn ($since) => (int) OrganizationTimeEntry::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->sum('minutes');

        return response()->json(['data' => [
            'tracked_24h_min' => $sum(now()->subDay()),
            'tracked_month_min' => $sum(now()->startOfMonth()),
        ]]);
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

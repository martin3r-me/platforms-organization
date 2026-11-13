<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Contracts\AgentMemoryStore;
use Platform\Organization\Models\OrganizationEntity;

/**
 * GEDÄCHTNIS-KURATIERUNG als Agent-Vertrag: der Daemon spiegelt seine steuernden Vault-Dateien in die Org
 * (POST /memory), holt offene Kuratier-Aufträge ab (GET /curations) und quittiert sie (POST /curations/{id}).
 * Die Org schreibt nie selbst ins Vault — das Engram wendet an, mit Sicherung und nur auf unverändertem Stand.
 *
 * Die Org hält nur die Schnittstelle: gespeichert und kuratiert wird beim gebundenen AgentMemoryStore (people).
 * Ohne Bindung antwortet sie 503 — das Engram versucht es beim nächsten Takt erneut.
 */
class AgentMemoryController extends Controller
{
    private const MAX_DOCS = 400;

    /** POST /api/org/agent/memory — vollständiger Spiegel; was fehlt, ist im Vault weg. */
    public function push(Request $request): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        $store = $this->store();
        if (! $store) {
            return response()->json(['message' => 'Agent memory store unavailable'], 503);
        }

        $data = $request->validate([
            'docs' => 'present|array|max:' . self::MAX_DOCS,
            'docs.*.path' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+\.md$/', 'not_regex:/\.\./'],
            'docs.*.kind' => 'required|string|in:' . implode(',', AgentMemoryStore::DOC_KINDS),
            'docs.*.title' => 'nullable|string|max:255',
            'docs.*.sha' => 'required|string|size:64',
            'docs.*.content' => 'present|nullable|string|max:400000',
            'docs.*.modified_at' => 'nullable|integer',
        ]);

        $stored = $store->storeDocs((int) $agent->id, $data['docs']);

        return response()->json(['data' => ['ok' => true] + $stored]);
    }

    /** POST /api/org/agent/rules — vollständiger Spiegel der Regeln (inkl. abgelöster) + Stand des Umzugs. */
    public function rules(Request $request): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        $store = $this->store();
        if (! $store) {
            return response()->json(['message' => 'Agent memory store unavailable'], 503);
        }

        $data = $request->validate([
            'rules' => 'present|array|max:3000',
            'rules.*.id' => 'required|integer|min:1',
            'rules.*.ord' => 'nullable|integer',
            'rules.*.kind' => 'required|string|in:step,rule,avoid',
            'rules.*.trigger' => 'nullable|string|max:500',
            'rules.*.text' => 'required|string|max:4000',
            'rules.*.why' => 'nullable|string|max:2000',
            'rules.*.source' => 'nullable|string|max:16',
            'rules.*.status' => 'required|string|in:proposed,probation,active,superseded,retired',
            'rules.*.supersedes' => 'nullable|integer|min:0',
            'rules.*.confirmed' => 'nullable|integer|min:0',
            'rules.*.refuted' => 'nullable|integer|min:0',
            'rules.*.sha' => 'required|string|size:64',
            'rules.*.created_at' => 'nullable|string|max:40',
            'rules.*.updated_at' => 'nullable|string|max:40',
            'rules.*.last_used_at' => 'nullable|string|max:40',
            'proposed_sha' => 'nullable|string|size:64',
            'mode' => 'nullable|string|in:rules,routine',
        ]);

        $stored = $store->storeRules((int) $agent->id, $data['rules']);

        $profile = $agent->agentProfile;
        if ($profile) {
            $settings = is_array($profile->settings) ? $profile->settings : [];
            $settings['rules'] = [
                'mode' => $data['mode'] ?? 'routine',
                'proposed_sha' => $data['proposed_sha'] ?? null,
                'pushed_at' => now()->toIso8601String(),
            ];
            $profile->settings = $settings;
            $profile->save();
        }

        return response()->json(['data' => ['ok' => true] + $stored]);
    }

    /** GET /api/org/agent/curations — offene Aufträge, älteste zuerst. */
    public function pending(Request $request): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        $store = $this->store();
        if (! $store) {
            return response()->json(['message' => 'Agent memory store unavailable'], 503);
        }

        return response()->json(['data' => $store->pendingCurations((int) $agent->id, 20)]);
    }

    /** POST /api/org/agent/curations/{id} — Quittung: applied | stale | rejected (+ Ergebnis). */
    public function ack(Request $request, int $id): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        $store = $this->store();
        if (! $store) {
            return response()->json(['message' => 'Agent memory store unavailable'], 503);
        }

        $data = $request->validate([
            'status' => 'required|string|in:applied,stale,rejected',
            'result' => 'nullable|string|max:2000',
        ]);

        $status = $store->acknowledgeCuration((int) $agent->id, $id, $data['status'], $data['result'] ?? null);
        if ($status === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => ['ok' => true, 'status' => $status]]);
    }

    private function store(): ?AgentMemoryStore
    {
        return app()->bound(AgentMemoryStore::class) ? app(AgentMemoryStore::class) : null;
    }

    private function agentFor(Request $request): ?OrganizationEntity
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return null;
        }

        return OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
    }
}

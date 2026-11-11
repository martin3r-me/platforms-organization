<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Platform\Organization\Models\OrganizationAgentCuration;
use Platform\Organization\Models\OrganizationAgentMemoryDoc;
use Platform\Organization\Models\OrganizationEntity;

/**
 * GEDÄCHTNIS-KURATIERUNG als Agent-Vertrag: der Daemon spiegelt seine steuernden Vault-Dateien in die Org
 * (POST /memory), holt offene Kuratier-Aufträge ab (GET /curations) und quittiert sie (POST /curations/{id}).
 * Die Org schreibt nie selbst ins Vault — das Engram wendet an, mit Sicherung und nur auf unverändertem Stand.
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

        $data = $request->validate([
            'docs' => 'present|array|max:' . self::MAX_DOCS,
            'docs.*.path' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+\.md$/', 'not_regex:/\.\./'],
            'docs.*.kind' => 'required|string|in:' . implode(',', OrganizationAgentMemoryDoc::KINDS),
            'docs.*.title' => 'nullable|string|max:255',
            'docs.*.sha' => 'required|string|size:64',
            'docs.*.content' => 'present|nullable|string|max:400000',
            'docs.*.modified_at' => 'nullable|integer',
        ]);

        $entityId = (int) $agent->id;
        $existing = OrganizationAgentMemoryDoc::where('organization_entity_id', $entityId)->get()->keyBy('path');
        $seen = [];
        foreach ($data['docs'] as $d) {
            $seen[$d['path']] = true;
            $row = $existing[$d['path']] ?? new OrganizationAgentMemoryDoc(['organization_entity_id' => $entityId, 'path' => $d['path']]);
            if ($row->exists && $row->sha === $d['sha']) {
                continue;
            }
            $row->fill([
                'kind' => $d['kind'],
                'title' => $d['title'] ?? null,
                'sha' => $d['sha'],
                'content' => (string) ($d['content'] ?? ''),
                'file_modified_at' => isset($d['modified_at']) ? Carbon::createFromTimestamp($d['modified_at']) : null,
            ])->save();
        }
        $gone = $existing->keys()->reject(fn ($p) => isset($seen[$p]))->all();
        if ($gone) {
            OrganizationAgentMemoryDoc::where('organization_entity_id', $entityId)->whereIn('path', $gone)->delete();
        }

        return response()->json(['data' => ['ok' => true, 'docs' => count($seen), 'removed' => count($gone)]]);
    }

    /** GET /api/org/agent/curations — offene Aufträge, älteste zuerst. */
    public function pending(Request $request): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        $items = OrganizationAgentCuration::where('organization_entity_id', $agent->id)
            ->where('status', 'pending')->orderBy('id')->limit(20)->get()
            ->map(fn (OrganizationAgentCuration $c) => [
                'id' => $c->id,
                'path' => $c->path,
                'op' => $c->op,
                'base_sha' => $c->base_sha,
                'content' => $c->content,
                'note' => $c->note,
            ])->values();

        return response()->json(['data' => $items]);
    }

    /** POST /api/org/agent/curations/{id} — Quittung: applied | stale | rejected (+ Ergebnis). */
    public function ack(Request $request, int $id): JsonResponse
    {
        $agent = $this->agentFor($request);
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }

        $data = $request->validate([
            'status' => 'required|string|in:applied,stale,rejected',
            'result' => 'nullable|string|max:2000',
        ]);

        $c = OrganizationAgentCuration::where('organization_entity_id', $agent->id)->find($id);
        if (! $c) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if ($c->status === 'pending') {
            $c->status = $data['status'];
            $c->result = $data['result'] ?? null;
            $c->applied_at = now();
            $c->save();
        }

        return response()->json(['data' => ['ok' => true, 'status' => $c->status]]);
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

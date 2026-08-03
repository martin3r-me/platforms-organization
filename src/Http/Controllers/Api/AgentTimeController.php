<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Services\StoreTimeEntry;

/**
 * Generischer „Stempel-Server" für autonome Worker: bucht Laufzeit + eine knappe Notiz auf
 * ein beliebiges (erlaubtes) Kontext-Item — modul-agnostisch, EIN Endpoint für alle Rollen.
 *
 * Das Stempeln ist ein Querschnitt: die Worker-Schleife ruft diesen Endpoint für JEDEN
 * Vorgang (Erledigt/Rückfrage), egal welche Rolle. `context_type` ist die FQCN des Items
 * (DevIssue/PlannerTask/HelpdeskTicket …) — bewusst FQCN, damit die Zeit im Org-Reporting
 * (EntityTimeResolver matcht auf FQCN, nicht auf Morph-Alias) korrekt aufrollt.
 */
class AgentTimeController extends Controller
{
    /** FQCNs, auf die ein Agent Zeit stempeln darf. Team-Scope kommt aus dem Item. */
    private const ALLOWED = [
        'Platform\Dev\Models\DevIssue',
        'Platform\Planner\Models\PlannerTask',
        'Platform\Helpdesk\Models\HelpdeskTicket',
        // Assistenz-Zeit wird auf den betreuten User („Chef") gebucht — Attribution der
        // sonst unsichtbaren Assistenz-Arbeit („N min Assistenz für Martin"). Team = das
        // aktuelle Team des Users (User hat kein eigenes team_id).
        'Platform\Core\Models\User',
    ];

    public function stamp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED)],
            'context_id' => 'required|integer|min:1',
            'minutes' => 'required|integer|min:1|max:100000',
            'note' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Item laden (Allowlist → FQCN existiert) → autoritatives team_id + Existenzprüfung.
        $ct = $data['context_type'];
        if (! class_exists($ct)) {
            return response()->json(['message' => 'Unknown context type'], 422);
        }
        $item = $ct::find((int) $data['context_id']);
        if (! $item) {
            return response()->json(['message' => 'Context item not found'], 404);
        }

        if (! class_exists(StoreTimeEntry::class)) {
            return response()->json(['message' => 'Time tracking unavailable'], 501);
        }

        // Autoritatives Team aus dem Item; ein User hat kein eigenes team_id → aktuelles Team.
        $teamId = $item->team_id ?? ($item->current_team_id ?? null);
        if (! $teamId) {
            return response()->json(['message' => 'No team context for item'], 422);
        }

        $entry = app(StoreTimeEntry::class)->store([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'context_type' => $ct,
            'context_id' => (int) $data['context_id'],
            'work_date' => now()->toDateString(),
            'minutes' => (int) $data['minutes'],
            'note' => $data['note'] ?? null,
            'metadata' => ['source' => 'agent'],
        ]);

        Log::info('[Org Agent] Time stamped', [
            'context_type' => $ct,
            'context_id' => (int) $data['context_id'],
            'minutes' => (int) $data['minutes'],
            'entry_id' => $entry->id,
        ]);

        return response()->json(['data' => ['id' => $entry->id, 'minutes' => $entry->minutes]]);
    }
}

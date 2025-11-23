<?php

namespace Platform\Organization\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Datawarehouse API Controller für SOLL-Zeiten (Planned Times)
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams) über User-Team-Zuordnung.
 */
class TimePlannedDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für SOLL-Zeiten
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = OrganizationTimePlanned::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'planned_minutes'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // User- und Team-Relationen laden
        $query->with('user:id,name,email', 'team:id,name');
        $timePlanned = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $timePlanned->map(function ($planned) {
            return [
                'id' => $planned->id,
                'uuid' => $planned->uuid,
                'team_id' => $planned->team_id,
                'team_name' => $planned->team?->name, // Team-Name mitliefern (denormalisiert)
                'user_id' => $planned->user_id,
                'user_name' => $planned->user?->name, // User-Name mitliefern (denormalisiert)
                'user_email' => $planned->user?->email, // User-Email mitliefern
                'context_type' => $planned->context_type,
                'context_id' => $planned->context_id,
                'planned_minutes' => $planned->planned_minutes,
                'hours' => $planned->hours, // Berechnetes Attribut
                'hours_formatted' => OrganizationTimeEntry::formatMinutesAsHours($planned->planned_minutes),
                'note' => $planned->note,
                'is_active' => $planned->is_active,
                'created_at' => $planned->created_at->toIso8601String(),
                'updated_at' => $planned->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $timePlanned->setCollection($formatted),
            'SOLL-Zeiten erfolgreich geladen'
        );
    }

    /**
     * Wendet alle Filter auf die Query an
     */
    protected function applyFilters($query, Request $request): void
    {
        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Team-Filter mit Kind-Teams Option (über User-Team-Zuordnung)
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            // Standardmäßig Kind-Teams inkludieren (wenn nicht explizit false)
            $includeChildrenValue = $request->input('include_child_teams');
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                // Team mit Kind-Teams laden
                $team = Team::find($teamId);
                
                if ($team) {
                    // Alle Team-IDs inkl. Kind-Teams sammeln
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    // User-IDs finden, die zu diesen Teams gehören
                    $userIds = DB::table('team_user')
                        ->whereIn('team_id', $teamIds)
                        ->pluck('user_id')
                        ->unique()
                        ->toArray();
                    
                    if (!empty($userIds)) {
                        $query->whereIn('user_id', $userIds);
                    } else {
                        // Keine User in diesen Teams - leeres Ergebnis
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    // Team nicht gefunden - leeres Ergebnis
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Nur das genannte Team (wenn explizit deaktiviert)
                $userIds = DB::table('team_user')
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->unique()
                    ->toArray();
                
                if (!empty($userIds)) {
                    $query->whereIn('user_id', $userIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        // Context-Filter
        if ($request->has('context_type')) {
            $query->where('context_type', $request->context_type);
        }
        if ($request->has('context_id')) {
            $query->where('context_id', $request->context_id);
        }

        // Aktiv-Filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Erstellt heute
        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Erstellt in Range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Geplante Minuten-Filter
        if ($request->has('planned_minutes_min')) {
            $query->where('planned_minutes', '>=', $request->planned_minutes_min);
        }
        if ($request->has('planned_minutes_max')) {
            $query->where('planned_minutes', '<=', $request->planned_minutes_max);
        }

        // Hat Notizen
        if ($request->has('has_note')) {
            if ($request->has_note === 'true' || $request->has_note === '1') {
                $query->whereNotNull('note')
                      ->where('note', '!=', '');
            } else {
                $query->where(function($q) {
                    $q->whereNull('note')
                      ->orWhere('note', '');
                });
            }
        }
    }

    /**
     * Health Check Endpoint
     * Gibt einen Beispiel-Datensatz zurück für Tests
     */
    public function health(Request $request)
    {
        try {
            $example = OrganizationTimePlanned::with('user:id,name,email', 'team:id,name')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$example) {
                return $this->success([
                    'status' => 'ok',
                    'message' => 'API ist erreichbar, aber keine Time Planned Einträge vorhanden',
                    'example' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 'Health Check');
            }

            $exampleData = [
                'id' => $example->id,
                'uuid' => $example->uuid,
                'team_id' => $example->team_id,
                'team_name' => $example->team?->name,
                'user_id' => $example->user_id,
                'user_name' => $example->user?->name,
                'user_email' => $example->user?->email,
                'context_type' => $example->context_type,
                'context_id' => $example->context_id,
                'planned_minutes' => $example->planned_minutes,
                'hours' => $example->hours,
                'hours_formatted' => OrganizationTimeEntry::formatMinutesAsHours($example->planned_minutes),
                'note' => $example->note,
                'is_active' => $example->is_active,
                'created_at' => $example->created_at->toIso8601String(),
                'updated_at' => $example->updated_at->toIso8601String(),
            ];

            return $this->success([
                'status' => 'ok',
                'message' => 'API ist erreichbar',
                'example' => $exampleData,
                'timestamp' => now()->toIso8601String(),
            ], 'Health Check');

        } catch (\Exception $e) {
            return $this->error('Health Check fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }
}


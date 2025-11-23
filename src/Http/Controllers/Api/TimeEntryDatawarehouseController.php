<?php

namespace Platform\Organization\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Datawarehouse API Controller für IST-Zeiten (Time Entries)
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams) über User-Team-Zuordnung.
 */
class TimeEntryDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für IST-Zeiten
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = OrganizationTimeEntry::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'work_date');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'work_date', 'created_at', 'updated_at', 'minutes', 'amount_cents'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('work_date', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // User- und Team-Relationen laden
        $query->with('user:id,name,email', 'team:id,name');
        $timeEntries = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $timeEntries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'team_id' => $entry->team_id,
                'team_name' => $entry->team?->name, // Team-Name mitliefern (denormalisiert)
                'user_id' => $entry->user_id,
                'user_name' => $entry->user?->name, // User-Name mitliefern (denormalisiert)
                'user_email' => $entry->user?->email, // User-Email mitliefern
                'context_type' => $entry->context_type,
                'context_id' => $entry->context_id,
                'root_context_type' => $entry->root_context_type,
                'root_context_id' => $entry->root_context_id,
                'work_date' => $entry->work_date->format('Y-m-d'),
                'minutes' => $entry->minutes,
                'hours' => $entry->hours, // Berechnetes Attribut
                'hours_formatted' => OrganizationTimeEntry::formatMinutesAsHours($entry->minutes),
                'rate_cents' => $entry->rate_cents,
                'rate_euros' => $entry->rate_cents ? round($entry->rate_cents / 100, 2) : null,
                'amount_cents' => $entry->amount_cents,
                'amount_euros' => $entry->amount_cents ? round($entry->amount_cents / 100, 2) : null,
                'is_billed' => $entry->is_billed,
                'has_key_result' => $entry->has_key_result ?? false,
                'metadata' => $entry->metadata,
                'note' => $entry->note,
                'source_module' => $entry->source_module, // Berechnetes Attribut
                'source_module_title' => $entry->source_module_title, // Berechnetes Attribut
                'created_at' => $entry->created_at->toIso8601String(),
                'updated_at' => $entry->updated_at->toIso8601String(),
                'deleted_at' => $entry->deleted_at?->toIso8601String(),
            ];
        });

        return $this->paginated(
            $timeEntries->setCollection($formatted),
            'IST-Zeiten erfolgreich geladen'
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
        if ($request->has('root_context_type')) {
            $query->where('root_context_type', $request->root_context_type);
        }
        if ($request->has('root_context_id')) {
            $query->where('root_context_id', $request->root_context_id);
        }

        // Datums-Filter
        if ($request->has('work_date')) {
            $query->whereDate('work_date', $request->work_date);
        }

        // Datums-Range
        if ($request->has('work_date_from')) {
            $query->whereDate('work_date', '>=', $request->work_date_from);
        }
        if ($request->has('work_date_to')) {
            $query->whereDate('work_date', '<=', $request->work_date_to);
        }

        // Heute
        if ($request->boolean('today')) {
            $query->whereDate('work_date', Carbon::today());
        }

        // Diese Woche
        if ($request->boolean('this_week')) {
            $query->whereBetween('work_date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ]);
        }

        // Dieser Monat
        if ($request->boolean('this_month')) {
            $query->whereMonth('work_date', Carbon::now()->month)
                  ->whereYear('work_date', Carbon::now()->year);
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

        // Minuten-Filter
        if ($request->has('minutes_min')) {
            $query->where('minutes', '>=', $request->minutes_min);
        }
        if ($request->has('minutes_max')) {
            $query->where('minutes', '<=', $request->minutes_max);
        }

        // Billing-Filter
        if ($request->has('is_billed')) {
            $query->where('is_billed', $request->boolean('is_billed'));
        }

        // Rate-Filter
        if ($request->has('has_rate')) {
            if ($request->has_rate === 'true' || $request->has_rate === '1') {
                $query->whereNotNull('rate_cents');
            } else {
                $query->whereNull('rate_cents');
            }
        }

        // Amount-Filter
        if ($request->has('has_amount')) {
            if ($request->has_amount === 'true' || $request->has_amount === '1') {
                $query->whereNotNull('amount_cents');
            } else {
                $query->whereNull('amount_cents');
            }
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

        // Nur gelöschte Einträge
        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        }

        // Mit gelöschten Einträgen
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }
    }
}


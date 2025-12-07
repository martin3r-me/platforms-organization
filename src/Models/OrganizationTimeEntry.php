<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationTimeEntry extends Model
{
    use SoftDeletes;

    protected $table = 'organization_time_entries';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'context_type',
        'context_id',
        'root_context_type',
        'root_context_id',
        'work_date',
        'minutes',
        'rate_cents',
        'amount_cents',
        'is_billed',
        'has_key_result',
        'metadata',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'minutes' => 'integer',
        'rate_cents' => 'integer',
        'amount_cents' => 'integer',
        'is_billed' => 'boolean',
        'has_key_result' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $entry->uuid = $uuid;

            if (! $entry->team_id && Auth::user()?->currentTeamRelation) {
                $entry->team_id = Auth::user()->currentTeamRelation->id; // Child-Team (nicht dynamisch)
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function rootContext(): MorphTo
    {
        return $this->morphTo('rootContext', 'root_context_type', 'root_context_id');
    }

    public function additionalContexts(): HasMany
    {
        return $this->hasMany(OrganizationTimeEntryContext::class, 'time_entry_id');
    }

    public function scopeForContextKey($query, string $type, int $id)
    {
        return $query->where('context_type', $type)
            ->where('context_id', $id);
    }

    /**
     * Scope für Abfragen über Kontext-Kaskade.
     * Findet alle Time-Entries, die über ihre Contexts zu einem bestimmten Kontext gehören.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type Model-Klasse
     * @param int $id Model-ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForContext($query, string $type, int $id)
    {
        return $query->whereHas('additionalContexts', function ($q) use ($type, $id) {
            $q->where('context_type', $type)
              ->where('context_id', $id);
        });
    }

    /**
     * Scope: Alle Time-Entries mit gegebenem Root-Context.
     * Berücksichtigt direkte root_context_* Felder und zusätzliche Kontexte mit is_root = true.
     */
    public function scopeForRootContext($query, string $type, int $id)
    {
        return $query->where(function ($q) use ($type, $id) {
            $q->where(function ($rq) use ($type, $id) {
                $rq->where('root_context_type', $type)
                   ->where('root_context_id', $id);
            })->orWhereHas('additionalContexts', function ($aq) use ($type, $id) {
                $aq->where('is_root', true)
                   ->where('context_type', $type)
                   ->where('context_id', $id);
            });
        });
    }

    public function getHoursAttribute(): float
    {
        return round(($this->minutes ?? 0) / 60, 2);
    }

    /**
     * Formatiert Minuten in ein lesbares Format.
     * Wenn weniger als 1 Tag: "2h 30min"
     * Wenn 1 Tag oder mehr: "1d 2h 30min"
     * 
     * @param int $minutes
     * @return string
     */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes < 0) {
            return '0min';
        }

        $days = intval($minutes / 480);
        $remainingMinutes = $minutes % 480;
        $hours = intval($remainingMinutes / 60);
        $mins = $remainingMinutes % 60;

        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        
        if ($mins > 0 || empty($parts)) {
            $parts[] = $mins . 'min';
        }

        return implode(' ', $parts);
    }

    /**
     * Gibt Minuten als dezimale Stunden zurück (z.B. "7,50 h").
     */
    public static function formatMinutesAsHours(int $minutes, int $precision = 2): string
    {
        if ($minutes < 0) {
            $minutes = 0;
        }

        $hours = $minutes / 60;

        return number_format($hours, $precision, ',', '.') . ' h';
    }

    /**
     * Gibt das Quellmodul basierend auf dem context_type zurück.
     * Extrahiert den Modul-Namen aus dem Namespace (z.B. "Platform\Planner\Models\PlannerTask" → "planner").
     * 
     * @return string|null
     */
    public function getSourceModuleAttribute(): ?string
    {
        if (!$this->context_type) {
            return null;
        }

        // Extrahiere Modul-Name aus Namespace
        // Format: Platform\{Module}\Models\{Model}
        if (preg_match('/Platform\\\\([^\\\\]+)\\\\/', $this->context_type, $matches)) {
            $moduleName = strtolower($matches[1]);
            
            // Bekannte Module-Mappings
            $moduleMappings = [
                'planner' => 'planner',
                'crm' => 'crm',
                'organization' => 'organization',
                'cms' => 'cms',
                'core' => 'core',
            ];
            
            return $moduleMappings[$moduleName] ?? $moduleName;
        }

        return null;
    }

    /**
     * Gibt den anzeigbaren Modul-Titel zurück.
     * 
     * @return string|null
     */
    public function getSourceModuleTitleAttribute(): ?string
    {
        $moduleKey = $this->source_module;
        
        if (!$moduleKey) {
            return null;
        }

        // Versuche Modul-Titel aus der Registry zu holen
        $module = \Platform\Core\PlatformCore::getModule($moduleKey);
        if ($module && isset($module['title'])) {
            return $module['title'];
        }

        // Fallback: Erster Buchstabe groß
        return ucfirst($moduleKey);
    }
}


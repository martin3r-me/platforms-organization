<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\UuidV7;

/**
 * Fremd-ID einer Entity: ihre Identität in einem anderen System.
 *
 * `system` = Namespace der Fremd-ID (kostenstelle, datev, buchungskonto,
 * kreditor, …), `value` = der Bezeichner dort. Die Kostenstelle ist nur
 * der erste `system`-Wert dieser Familie — kein Sonderfall.
 */
class OrganizationEntityExternalId extends Model
{
    protected $table = 'organization_entity_external_ids';

    /** Kanonisches System für die Kostenstelle. */
    public const SYSTEM_COST_CENTER = 'kostenstelle';

    protected $fillable = [
        'uuid',
        'entity_id',
        'system',
        'value',
        'label',
        'team_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
            if (empty($model->team_id) && Auth::user()?->currentTeam) {
                $model->team_id = Auth::user()->currentTeam->id;
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function scopeForSystem($query, string $system)
    {
        return $query->where('system', $system);
    }

    /**
     * Löst eine Fremd-ID zur zugehörigen Entity auf.
     * Kern des MCP-Resolvers: "hänge X an Kostenstelle KST-4200".
     */
    public static function resolveEntity(string $system, string $value, ?int $teamId = null): ?OrganizationEntity
    {
        $query = static::where('system', $system)->where('value', $value);

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->first()?->entity;
    }
}

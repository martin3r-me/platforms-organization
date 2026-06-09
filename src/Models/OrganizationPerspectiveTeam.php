<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\Team;

/**
 * Zuordnung: Carrier-Entity (= Perspektive) gehoert zu Plattform-Team.
 *
 * Pro Team kann genau einer dieser Eintraege is_default=true sein —
 * der Service erzwingt das beim Setzen, weil MySQL keine partial
 * Unique-Indizes auf einem Boolean unterstuetzt.
 */
class OrganizationPerspectiveTeam extends Model
{
    protected $table = 'organization_perspective_teams';

    protected $fillable = [
        'perspective_entity_id',
        'team_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function perspectiveEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'perspective_entity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForPerspective(Builder $query, int $perspectiveEntityId): Builder
    {
        return $query->where('perspective_entity_id', $perspectiveEntityId);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}

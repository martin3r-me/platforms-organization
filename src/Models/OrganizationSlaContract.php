<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationSlaContract extends Model
{
    use SoftDeletes;

    protected $table = 'organization_sla_contracts';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'response_time_hours',
        'resolution_time_hours',
        'error_tolerance_percent',
        'is_active',
    ];

    protected $casts = [
        'response_time_hours' => 'integer',
        'resolution_time_hours' => 'integer',
        'error_tolerance_percent' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sla) {
            if (empty($sla->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $sla->uuid = $uuid;
            }

            if (! $sla->user_id) {
                $sla->user_id = Auth::id();
            }

            if (! $sla->team_id) {
                $sla->team_id = Auth::user()?->currentTeamRelation?->id;
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

    public function relationshipInterlinks(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationEntityRelationshipInterlink::class,
            'organization_eri_sla_contracts',
            'sla_contract_id',
            'entity_relationship_interlink_id'
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationEntityRelationshipInterlink extends Model
{
    use SoftDeletes;

    protected $table = 'organization_entity_relationship_interlinks';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'entity_relationship_id',
        'interlink_id',
        'note',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pivot) {
            if (empty($pivot->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $pivot->uuid = $uuid;
            }

            if (! $pivot->user_id) {
                $pivot->user_id = Auth::id();
            }

            if (! $pivot->team_id) {
                $pivot->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });
    }

    public function entityRelationship(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntityRelationship::class, 'entity_relationship_id');
    }

    public function interlink(): BelongsTo
    {
        return $this->belongsTo(OrganizationInterlink::class, 'interlink_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

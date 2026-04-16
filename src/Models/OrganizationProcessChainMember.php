<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Enums\ChainMemberRole;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessChainMember extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_chain_members';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'chain_id',
        'process_id',
        'position',
        'role',
        'is_required',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'position'    => 'integer',
        'role'        => ChainMemberRole::class,
        'is_required' => 'boolean',
        'metadata'    => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });

        // Keep chain endpoint cache in sync
        static::saved(function (self $model) {
            $model->chain?->syncEndpointsFromMembers();
        });

        static::deleted(function (self $model) {
            $model->chain?->syncEndpointsFromMembers();
        });
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcessChain::class, 'chain_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'process_id');
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

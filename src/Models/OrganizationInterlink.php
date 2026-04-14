<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationInterlink extends Model
{
    use SoftDeletes;

    protected $table = 'organization_interlinks';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'url',
        'reference',
        'category_id',
        'type_id',
        'is_bidirectional',
        'is_active',
        'valid_from',
        'valid_to',
        'metadata',
        'owner_entity_id',
    ];

    protected $casts = [
        'is_bidirectional' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $interlink) {
            if (empty($interlink->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $interlink->uuid = $uuid;
            }

            if (! $interlink->user_id) {
                $interlink->user_id = Auth::id();
            }

            if (! $interlink->team_id) {
                $interlink->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'owner_entity_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OrganizationInterlinkCategory::class, 'category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganizationInterlinkType::class, 'type_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidNow($query)
    {
        $now = now()->toDateString();
        return $query->where(function ($q) use ($now) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $now);
        });
    }
}

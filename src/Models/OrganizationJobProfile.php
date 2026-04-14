<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * JobProfile = wiederverwendbare Stellenbeschreibung / Vertrags-Template.
 *
 * Wird Personen über OrganizationPersonJobProfile zugewiesen.
 */
class OrganizationJobProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_job_profiles';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'content',
        'level',
        'skills',
        'responsibilities',
        'status',
        'owner_entity_id',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'skills'           => 'array',
        'responsibilities' => 'array',
        'effective_from'   => 'date',
        'effective_to'     => 'date',
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
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'owner_entity_id');
    }

    /**
     * Alle Person-Zuweisungen dieses Profils.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(OrganizationPersonJobProfile::class, 'job_profile_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * Rollen-Katalog: wiederverwendbare Rollen-Definitionen wie
 * "Projektleiter", "Scrum Master", "Tech Lead".
 *
 * Werden Personen im Kontext einer beliebigen Entity über
 * OrganizationRoleAssignment zugewiesen.
 */
class OrganizationRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organization_roles';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'slug',
        'description',
        'vsm_system',
        'status',
        'owner_entity_id',
    ];

    /**
     * Erlaubte VSM-Systeme — synchron mit OrganizationEntityVsmAssignment::VSM_SYSTEMS.
     */
    public const VSM_SYSTEMS = ['s1', 's2', 's3', 's3_star', 's4', 's5'];

    /**
     * Lesbare Labels fuer UI-Anzeige.
     */
    public const VSM_LABELS = [
        's1' => 'S1 · Operation',
        's2' => 'S2 · Koordination',
        's3' => 'S3 · Kontrolle',
        's3_star' => 'S3* · Audit',
        's4' => 'S4 · Intelligenz',
        's5' => 'S5 · Identität',
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

        static::saving(function (self $model) {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = $model->generateUniqueSlug($model->name);
            }
        });
    }

    /**
     * Erzeugt einen pro Team eindeutigen Slug.
     */
    protected function generateUniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $i = 2;

        while (
            static::where('team_id', $this->team_id)
                ->where('slug', $candidate)
                ->where('id', '!=', $this->id)
                ->exists()
        ) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
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

    public function assignments(): HasMany
    {
        return $this->hasMany(OrganizationRoleAssignment::class, 'role_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

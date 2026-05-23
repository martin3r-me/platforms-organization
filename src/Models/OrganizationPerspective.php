<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationPerspective extends Model
{
    use SoftDeletes;

    protected $table = 'organization_perspectives';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'is_default',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
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

        // Ensure only one default per team
        static::saving(function (self $model) {
            if ($model->is_default) {
                self::where('team_id', $model->team_id)
                    ->where('id', '!=', $model->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function dimensionLinks()
    {
        return $this->hasMany(OrganizationDimensionLink::class, 'perspective_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public static function getDefault(int $teamId): ?self
    {
        return self::where('team_id', $teamId)
            ->where('is_default', true)
            ->first();
    }

    public static function getOrCreateDefault(int $teamId, ?int $userId = null): self
    {
        $perspective = self::getDefault($teamId);

        if (!$perspective) {
            $perspective = self::create([
                'team_id' => $teamId,
                'name' => 'Standard',
                'description' => 'Automatisch erstellte Standard-Perspektive',
                'is_default' => true,
                'created_by_user_id' => $userId,
            ]);
        }

        return $perspective;
    }
}

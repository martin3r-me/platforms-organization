<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Versionierter Snapshot des Forecast-Contents.
 * Der Forecast selbst zeigt via current_version_id auf die aktuelle Version.
 */
class OrganizationForecastVersion extends Model
{
    protected $table = 'organization_forecast_versions';

    protected $fillable = [
        'uuid',
        'forecast_id',
        'version',
        'content',
        'change_note',
        'user_id',
    ];

    protected $casts = [
        'version' => 'integer',
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

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(OrganizationForecast::class, 'forecast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

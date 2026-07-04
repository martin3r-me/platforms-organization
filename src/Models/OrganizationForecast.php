<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Strategisches Forecast einer Carrier-Entity.
 *
 * Content ist versionierbar; current_version_id zeigt auf die aktuell
 * dargestellte Version. FocusAreas haengen an einem Forecast.
 */
class OrganizationForecast extends Model implements HasDisplayName
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_forecasts';

    protected $fillable = [
        'uuid',
        'entity_id',
        'title',
        'target_date',
        'content',
        'current_version_id',
        'team_id',
        'user_id',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            $entity = OrganizationEntity::with('type')->find($model->entity_id);
            if (!$entity || $entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                throw new \InvalidArgumentException(
                    "entity_id muss auf eine Carrier-Entity zeigen (Entity #{$model->entity_id} "
                    . "ist '" . ($entity->type?->code ?? 'null') . "')."
                );
            }
        });
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    public function createNewVersion(string $content, ?string $changeNote = null): OrganizationForecastVersion
    {
        return DB::transaction(function () use ($content, $changeNote) {
            $nextVersion = 1 + (int) $this->versions()->max('version');

            $version = $this->versions()->create([
                'version' => $nextVersion,
                'content' => $content,
                'change_note' => $changeNote,
                'user_id' => auth()->id(),
            ]);

            $this->update([
                'content' => $content,
                'current_version_id' => $version->id,
            ]);

            return $version;
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(OrganizationForecastVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OrganizationForecastVersion::class, 'forecast_id')->orderBy('version', 'desc');
    }

    public function focusAreas(): HasMany
    {
        return $this->hasMany(OrganizationFocusArea::class, 'forecast_id')->orderBy('order');
    }
}

<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Strategisches Dokument: Mission oder Vision einer Carrier-Entity.
 *
 * Mission = warum die Organisation existiert (zeitlich stabil).
 * Vision  = gewollter Zukunftszustand (5-10 Jahre).
 *
 * Versionierbar; pro (entity_id, type) ist genau eine Version aktiv.
 */
class OrganizationStrategicDocument extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'organization_strategic_documents';

    protected $fillable = [
        'uuid',
        'entity_id',
        'type',
        'title',
        'content',
        'version',
        'is_active',
        'valid_from',
        'change_note',
        'team_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'version' => 'integer',
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

        static::creating(function (self $model) {
            if ($model->is_active) {
                self::where('entity_id', $model->entity_id)
                    ->where('type', $model->type)
                    ->update(['is_active' => false]);
            }

            if (empty($model->version)) {
                $model->version = 1 + (int) self::where('entity_id', $model->entity_id)
                    ->where('type', $model->type)
                    ->max('version');
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('is_active') && $model->is_active) {
                self::where('entity_id', $model->entity_id)
                    ->where('type', $model->type)
                    ->where('id', '!=', $model->id)
                    ->update(['is_active' => false]);
            }
        });
    }

    public function scopeActive($query, ?string $type = null)
    {
        $query->where('is_active', true);
        if ($type) {
            $query->where('type', $type);
        }
        return $query;
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function createNewVersion(array $attributes = []): self
    {
        return DB::transaction(function () use ($attributes) {
            $this->update(['is_active' => false]);

            return self::create(array_merge([
                'entity_id' => $this->entity_id,
                'type' => $this->type,
                'team_id' => $this->team_id,
                'title' => $this->title,
                'content' => $this->content,
                'version' => $this->version + 1,
                'is_active' => true,
                'valid_from' => now()->toDateString(),
            ], $attributes));
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

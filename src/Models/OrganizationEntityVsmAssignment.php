<?php

namespace Platform\Organization\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * VSM-Assignment: wer fuellt welche VSM-Zelle aus welcher Perspektive aus.
 *
 * Perspektive = Carrier-Entity (vsm_class === 'carrier' + can_be_perspective).
 * Assignee = Actor-Entity (vsm_class === 'actor').
 *
 * Mehrfachbesetzung pro Zelle erlaubt (z.B. S5 = Martin + Burkhard).
 * Constraints werden im Saving-Hook erzwungen.
 */
class OrganizationEntityVsmAssignment extends Model
{
    use SoftDeletes;

    public const VSM_S1 = 's1';
    public const VSM_S2 = 's2';
    public const VSM_S3 = 's3';
    public const VSM_S3_STAR = 's3_star';
    public const VSM_S4 = 's4';
    public const VSM_S5 = 's5';

    public const VSM_SYSTEMS = [
        self::VSM_S1,
        self::VSM_S2,
        self::VSM_S3,
        self::VSM_S3_STAR,
        self::VSM_S4,
        self::VSM_S5,
    ];

    protected $table = 'organization_entity_vsm_assignments';

    protected $fillable = [
        'uuid',
        'team_id',
        'perspective_entity_id',
        'vsm_system',
        'assigned_entity_id',
        'scope',
        'valid_from',
        'valid_until',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
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

            if (!in_array($model->vsm_system, self::VSM_SYSTEMS, true)) {
                throw new \InvalidArgumentException(
                    "vsm_system '{$model->vsm_system}' ist ungueltig. Erlaubt: "
                    . implode(', ', self::VSM_SYSTEMS)
                );
            }

            $perspective = OrganizationEntity::with('type')->find($model->perspective_entity_id);
            if (!$perspective) {
                throw new \InvalidArgumentException("perspective_entity_id {$model->perspective_entity_id} nicht gefunden.");
            }
            if ($perspective->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                throw new \InvalidArgumentException(
                    "perspective_entity_id muss ein Carrier-Entity-Typ sein (Entity #{$model->perspective_entity_id} "
                    . "ist '{$perspective->type?->code}' mit vsm_class '{$perspective->type?->vsm_class}')."
                );
            }

            $assignee = OrganizationEntity::with('type')->find($model->assigned_entity_id);
            if (!$assignee) {
                throw new \InvalidArgumentException("assigned_entity_id {$model->assigned_entity_id} nicht gefunden.");
            }
            if ($assignee->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_ACTOR) {
                throw new \InvalidArgumentException(
                    "assigned_entity_id muss ein Actor-Entity-Typ sein (Entity #{$model->assigned_entity_id} "
                    . "ist '{$assignee->type?->code}' mit vsm_class '{$assignee->type?->vsm_class}')."
                );
            }

            if ($model->valid_from && $model->valid_until && $model->valid_until->lt($model->valid_from)) {
                throw new \InvalidArgumentException("valid_until darf nicht vor valid_from liegen.");
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function perspectiveEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'perspective_entity_id');
    }

    public function assignedEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'assigned_entity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeForPerspective($query, int $perspectiveEntityId)
    {
        return $query->where('perspective_entity_id', $perspectiveEntityId);
    }

    public function scopeForSystem($query, string $vsmSystem)
    {
        return $query->where('vsm_system', $vsmSystem);
    }

    public function scopeForAssignee($query, int $assignedEntityId)
    {
        return $query->where('assigned_entity_id', $assignedEntityId);
    }

    /**
     * Nur Zuordnungen, die zum angegebenen Datum (default: heute) aktiv sind.
     * NULL-Bounds gelten als unbegrenzt offen.
     */
    public function scopeActiveAt($query, ?Carbon $date = null)
    {
        $date = $date ?? Carbon::today();

        return $query
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date);
            });
    }

    public function isActiveAt(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::today();

        if ($this->valid_from && $date->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $date->gt($this->valid_until)) {
            return false;
        }
        return true;
    }
}

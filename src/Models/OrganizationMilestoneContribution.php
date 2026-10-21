<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Services\ObjectTypeRegistry;
use Symfony\Component\Uid\UuidV7;

/**
 * Contribution: ein Objekt zahlt auf einen Meilenstein der Transformations-Map ein
 * (typisch: Objectives und Key Results — die Umsetzung zahlt auf die Strategie ein).
 *
 * LOSE gekoppelt: organization kennt keine fremden Typen. `linkable_type` wird über
 * die ObjectTypeRegistry auf einen Morph-Alias normalisiert (die Module registrieren
 * ihre Aliase selbst), die Existenz generisch über die Morph-Map geprüft — wie bei
 * brief/context. Kein Fremdschlüssel, auch nicht auf den Meilenstein; aufgeräumt wird
 * im Milestone-Model beim endgültigen Löschen.
 *
 * `weight` (1–100, optional) ist für spätere Fortschritts-Hochrechnung vorgesehen;
 * vorerst hält die Contribution nur die Ausrichtung fest.
 */
class OrganizationMilestoneContribution extends Model
{
    use LogsActivity;

    protected $table = 'organization_milestone_contributions';

    protected $fillable = [
        'uuid',
        'milestone_id',
        'linkable_type',
        'linkable_id',
        'weight',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'milestone_id' => 'integer',
        'linkable_id' => 'integer',
        'weight' => 'integer',
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

            $alias = ObjectTypeRegistry::resolve((string) $model->linkable_type);
            $class = $alias ? ObjectTypeRegistry::modelClass($alias) : null;
            if (!$class) {
                throw new \InvalidArgumentException("Objekttyp '{$model->linkable_type}' ist nicht registriert.");
            }
            $model->linkable_type = $alias;
            if (!$class::query()->whereKey($model->linkable_id)->exists()) {
                throw new \InvalidArgumentException("{$alias} #{$model->linkable_id} nicht gefunden.");
            }
            if (!OrganizationMilestone::query()->whereKey($model->milestone_id)->exists()) {
                throw new \InvalidArgumentException("Meilenstein #{$model->milestone_id} nicht gefunden.");
            }
            if ($model->weight !== null && ($model->weight < 1 || $model->weight > 100)) {
                throw new \InvalidArgumentException("weight muss zwischen 1 und 100 liegen (ist {$model->weight}).");
            }
        });
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(OrganizationMilestone::class, 'milestone_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Ausrichtung als Array (Tools + Agent-API): worauf zahlt es ein?
     */
    public function toAlignmentArray(): array
    {
        $m = $this->milestone;

        return [
            'contribution_id' => $this->id,
            'milestone_id' => $this->milestone_id,
            'milestone' => $m?->title,
            'focus_area' => $m?->focusArea?->title,
            'entity_id' => $m?->entity_id,
            'entity' => $m?->entity?->name,
            'target_year' => $m?->target_year,
            'target_quarter' => $m?->target_quarter,
            'weight' => $this->weight,
        ];
    }
}

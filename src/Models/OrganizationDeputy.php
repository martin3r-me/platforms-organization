<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Vertretung: deputy vertritt principal – überall oder nur an scope (und darunter), optional nur bei Abwesenheit. */
class OrganizationDeputy extends Model
{
    use SoftDeletes;

    protected $table = 'organization_deputies';

    protected $fillable = [
        'uuid', 'team_id', 'principal_entity_id', 'deputy_entity_id', 'scope_entity_id', 'only_when_absent',
        'valid_from', 'valid_to', 'note', 'created_by_user_id',
    ];

    protected $casts = [
        'only_when_absent' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->uuid ??= (string) Str::uuid();
        });
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'principal_entity_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'deputy_entity_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'scope_entity_id');
    }

    public function scopeValidAt(Builder $q, string $date): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }
}

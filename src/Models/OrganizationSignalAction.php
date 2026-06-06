<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationSignalAction extends Model
{
    use SoftDeletes;

    protected $table = 'organization_signal_actions';

    protected $fillable = [
        'uuid',
        'signal_id',
        'position',
        'title',
        'description',
        'status',
        'decision_reason',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignal::class, 'signal_id');
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeDecided(Builder $query): Builder
    {
        return $query->whereIn('status', ['applied', 'dismissed']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

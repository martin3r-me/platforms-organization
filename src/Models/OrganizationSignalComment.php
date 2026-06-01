<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationSignalComment extends Model
{
    protected $table = 'organization_signal_comments';

    protected $fillable = [
        'uuid',
        'signal_id',
        'parent_id',
        'user_id',
        'author_context',
        'content',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function scopeRootComments(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}

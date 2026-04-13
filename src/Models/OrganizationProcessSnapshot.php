<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'organization_process_snapshots';

    protected $fillable = [
        'uuid',
        'process_id',
        'version',
        'label',
        'snapshot_data',
        'metrics',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'version'       => 'integer',
        'snapshot_data'  => 'array',
        'metrics'        => 'array',
        'created_at'     => 'datetime',
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
            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'process_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

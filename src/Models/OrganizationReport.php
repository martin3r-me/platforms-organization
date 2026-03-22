<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationReport extends Model
{
    use SoftDeletes;

    protected $table = 'organization_reports';

    protected $fillable = [
        'uuid',
        'team_id',
        'report_type_id',
        'entity_id',
        'user_id',
        'snapshot_at',
        'generated_content',
        'status',
        'output_channel',
        'obsidian_path',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function reportType()
    {
        return $this->belongsTo(OrganizationReportType::class, 'report_type_id');
    }

    public function entity()
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by');
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

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
}

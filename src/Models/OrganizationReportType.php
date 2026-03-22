<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class OrganizationReportType extends Model
{
    use SoftDeletes;

    protected $table = 'organization_report_types';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'key',
        'description',
        'hull',
        'requirements',
        'modules',
        'include_time_entries',
        'frequency',
        'output_channel',
        'obsidian_folder',
        'template',
        'data_sources',
        'ai_sections',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'hull' => 'array',
        'requirements' => 'array',
        'modules' => 'array',
        'include_time_entries' => 'boolean',
        'data_sources' => 'array',
        'ai_sections' => 'array',
        'is_active' => 'boolean',
    ];

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by');
    }

    public function reports()
    {
        return $this->hasMany(OrganizationReport::class, 'report_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function usesTemplateEngine(): bool
    {
        return !empty($this->template);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
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

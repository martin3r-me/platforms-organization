<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationTimePlannedContext extends Model
{
    protected $table = 'organization_time_planned_contexts';

    protected $fillable = [
        'planned_id',
        'context_type',
        'context_id',
        'depth',
        'is_primary',
        'is_root',
        'context_label',
    ];

    protected $casts = [
        'depth' => 'integer',
        'is_primary' => 'boolean',
        'is_root' => 'boolean',
    ];

    public function planned(): BelongsTo
    {
        return $this->belongsTo(OrganizationTimePlanned::class, 'planned_id');
    }
}


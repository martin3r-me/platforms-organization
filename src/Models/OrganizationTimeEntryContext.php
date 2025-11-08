<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationTimeEntryContext extends Model
{
    protected $table = 'organization_time_entry_contexts';

    protected $fillable = [
        'time_entry_id',
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

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(OrganizationTimeEntry::class, 'time_entry_id');
    }
}


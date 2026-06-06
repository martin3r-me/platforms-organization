<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

class OrganizationSignalFocus extends Model
{
    protected $table = 'organization_signal_focuses';

    protected $fillable = [
        'signal_id',
        'user_id',
        'focused_at',
    ];

    protected $casts = [
        'focused_at' => 'datetime',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignal::class, 'signal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

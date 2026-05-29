<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInquiryRecipient extends Model
{
    protected $table = 'organization_inquiry_recipients';

    protected $fillable = [
        'inquiry_id',
        'recipient_entity_id',
        'recipient_user_id',
        'channel',
        'status',
        'response',
        'response_at',
        'sent_at',
        'reminded_at',
    ];

    protected $casts = [
        'response' => 'array',
        'response_at' => 'datetime',
        'sent_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(OrganizationInquiry::class, 'inquiry_id');
    }

    public function recipientEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'recipient_entity_id');
    }
}

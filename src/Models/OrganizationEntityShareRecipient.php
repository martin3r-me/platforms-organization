<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eingeladene Person einer Freigabe — über ihren persönlichen Link erkennbar
 * (Zugriffe je Person, einzeln entziehbar).
 */
class OrganizationEntityShareRecipient extends Model
{
    protected $table = 'organization_entity_share_recipients';

    protected $fillable = [
        'share_id',
        'email',
        'token',
        'invited_at',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $recipient) {
            $recipient->email = mb_strtolower(trim($recipient->email));
            $recipient->token ??= OrganizationEntityShare::newToken();
        });
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntityShare::class, 'share_id');
    }

    public function url(): string
    {
        return route('organization.share.public', ['token' => $this->token]);
    }
}

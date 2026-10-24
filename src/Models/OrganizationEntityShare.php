<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * Freigabe eines Knotens nach außen — das Kunden-Tor.
 *
 * Ein Knoten wird mit allem geteilt, was daran hängt; was sichtbar ist, regeln
 * `types` (Morph-Aliase, null = alle mit Kundenansicht) und `depth` (Ebenen im Baum,
 * null = alles darunter). Zugang: `link` (jeder mit der URL) oder `invite`
 * (nur eingeladene Adressen, jede mit eigenem Link).
 */
class OrganizationEntityShare extends Model
{
    public const ACCESS_LINK = 'link';
    public const ACCESS_INVITE = 'invite';

    protected $table = 'organization_entity_shares';

    protected $fillable = [
        'uuid',
        'team_id',
        'entity_id',
        'token',
        'title',
        'message',
        'access',
        'depth',
        'types',
        'settings',
        'expires_at',
        'revoked_at',
        'created_by_user_id',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'depth' => 'integer',
        'types' => 'array',
        'settings' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $share) {
            $share->uuid ??= (string) Str::uuid();
            $share->token ??= static::newToken();
            $share->access ??= self::ACCESS_LINK;
        });
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OrganizationEntityShareRecipient::class, 'share_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isInviteOnly(): bool
    {
        return $this->access === self::ACCESS_INVITE;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function url(?string $token = null): string
    {
        return route('organization.share.public', ['token' => $token ?? $this->token]);
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->entity?->name ?? 'Freigabe');
    }
}

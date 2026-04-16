<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Enums\ChainMemberRole;
use Platform\Organization\Enums\ProcessChainType;
use Symfony\Component\Uid\UuidV7;

class OrganizationProcessChain extends Model
{
    use SoftDeletes;

    protected $table = 'organization_process_chains';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'code',
        'description',
        'chain_type',
        'is_active',
        'is_auto_detected',
        'entry_process_id',
        'exit_process_id',
        'metadata',
    ];

    protected $casts = [
        'chain_type'       => ProcessChainType::class,
        'is_active'        => 'boolean',
        'is_auto_detected' => 'boolean',
        'metadata'         => 'array',
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

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()?->currentTeamRelation?->id;
            }
        });

        // Sync entry_process_id / exit_process_id from member role
        static::saved(function (self $model) {
            // no-op on chain itself; see OrganizationProcessChainMember observer logic
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationProcessChainMember::class, 'chain_id')
            ->orderBy('position');
    }

    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationProcess::class,
            'organization_process_chain_members',
            'chain_id',
            'process_id'
        )
            ->withPivot(['id', 'uuid', 'position', 'role', 'is_required', 'notes', 'metadata'])
            ->withTimestamps()
            ->orderBy('organization_process_chain_members.position');
    }

    public function entryProcess(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'entry_process_id');
    }

    public function exitProcess(): BelongsTo
    {
        return $this->belongsTo(OrganizationProcess::class, 'exit_process_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeAutoDetected($query)
    {
        return $query->where('is_auto_detected', true);
    }

    public function scopePromoted($query)
    {
        return $query->where('is_auto_detected', false)
            ->where('chain_type', '!=', ProcessChainType::AdHoc->value);
    }

    /**
     * Recompute entry_process_id / exit_process_id from current members' roles.
     * Chain members are the source of truth; these FKs are a cache.
     */
    public function syncEndpointsFromMembers(): void
    {
        $entry = $this->members()
            ->where('role', ChainMemberRole::Entry->value)
            ->orderBy('position')
            ->first();

        $exit = $this->members()
            ->where('role', ChainMemberRole::Exit->value)
            ->orderByDesc('position')
            ->first();

        $dirty = false;
        $newEntryId = $entry?->process_id;
        $newExitId = $exit?->process_id;

        if ($this->entry_process_id !== $newEntryId) {
            $this->entry_process_id = $newEntryId;
            $dirty = true;
        }

        if ($this->exit_process_id !== $newExitId) {
            $this->exit_process_id = $newExitId;
            $dirty = true;
        }

        if ($dirty) {
            $this->saveQuietly();
        }
    }
}

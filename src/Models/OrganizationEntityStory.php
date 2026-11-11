<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erzählung eines Knotens für einen Zeitraum (siehe EntityFactsService / EntityStoryWriter).
 */
class OrganizationEntityStory extends Model
{
    protected $table = 'organization_entity_stories';

    protected $fillable = [
        'team_id',
        'entity_id',
        'period',
        'as_of',
        'facts',
        'story',
        'source',
        'model',
        'generated_at',
    ];

    protected $casts = [
        'facts' => 'array',
        'as_of' => 'date',
        'generated_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'entity_id');
    }

    /** Neueste Erzählung eines Knotens für einen Zeitraum. */
    public static function latestFor(int $entityId, string $period = 'week'): ?self
    {
        return static::where('entity_id', $entityId)->where('period', $period)->orderByDesc('as_of')->first();
    }
}

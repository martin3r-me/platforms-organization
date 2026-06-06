<?php

namespace Platform\Organization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

class OrganizationSynthesisPromptDefinition extends Model
{
    use SoftDeletes;

    protected $table = 'organization_synthesis_prompt_definitions';

    public const REPORT_TYPES = ['weekly', 'monthly', 'quarterly'];

    public const DEFAULT_SYSTEM_PROMPT = 'Du bist ein strategischer Analyst der einen Synthese-Report für die Organisationsleitung erstellt. '
        . 'Dein Report verdichtet alle diagnostischen Erkenntnisse einer Periode in eine strukturierte Zusammenfassung. '
        . 'Format: Markdown. Sprache: Deutsch. '
        . 'Abschnitte: 1) Executive Summary, 2) Algedonic Signals (existenzielle Bedrohungen, separat hervorgehoben), '
        . '3) S3-Steuerungsbefunde, 4) S3*-Audit-Befunde, 5) S2-Cross-Entity-Muster, '
        . '6) Empfehlungen, 7) Offene Inquiries.';

    public const DEFAULT_USER_TEMPLATE = "Erstelle einen {report_type} Synthese-Report für den Zeitraum {period_start} bis {period_end}.\n\n"
        . "Signale der Periode ({signals_count}):\n{signals_json}";

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'report_type',
        'system_prompt',
        'user_message_template',
        'max_signals',
        'model',
        'max_tokens',
        'is_active',
    ];

    protected $casts = [
        'max_signals' => 'integer',
        'max_tokens' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForReportType(Builder $query, string $reportType): Builder
    {
        return $query->where('report_type', $reportType);
    }

    /**
     * Renders the user message template with the given placeholder values.
     * Supported: {report_type} {period_start} {period_end} {signals_count} {signals_json}
     */
    public function renderUserMessage(array $values): string
    {
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($this->user_message_template, $replacements);
    }
}

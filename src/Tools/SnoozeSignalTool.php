<?php

namespace Platform\Organization\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalComment;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class SnoozeSignalTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.snooze';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signals/{id}/snooze - Setzt ein Signal auf Wiedervorlage. Das Signal wird bis zum Snooze-Zeitpunkt aus der Standard-Liste ausgeblendet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'signal_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Signals.',
                ],
                'duration' => [
                    'type' => 'string',
                    'description' => 'Optional: Vordefinierte Dauer. Wird ignoriert wenn snooze_until gesetzt.',
                    'enum' => ['1d', '3d', '1w', '2w', '1m'],
                ],
                'snooze_until' => [
                    'type' => 'string',
                    'description' => 'Optional: Exakter Wiedervorlage-Zeitpunkt (ISO 8601). Hat Vorrang vor duration.',
                ],
            ],
            'required' => ['signal_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $signalId = (int) ($arguments['signal_id'] ?? 0);
            if ($signalId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'signal_id ist erforderlich.');
            }

            $signal = OrganizationSignal::where('id', $signalId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $signal) {
                return ToolResult::error('NOT_FOUND', 'Signal nicht gefunden.');
            }

            // Resolve snooze_until
            $snoozeUntil = null;

            if (! empty($arguments['snooze_until'])) {
                try {
                    $snoozeUntil = Carbon::parse($arguments['snooze_until']);
                } catch (\Throwable) {
                    return ToolResult::error('VALIDATION_ERROR', 'snooze_until ist kein gültiges Datum (ISO 8601).');
                }
            } elseif (! empty($arguments['duration'])) {
                $snoozeUntil = match ($arguments['duration']) {
                    '1d' => now()->addDay(),
                    '3d' => now()->addDays(3),
                    '1w' => now()->addWeek(),
                    '2w' => now()->addWeeks(2),
                    '1m' => now()->addMonth(),
                    default => null,
                };
            }

            if (! $snoozeUntil) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder duration oder snooze_until muss angegeben werden.');
            }

            if ($snoozeUntil->isPast()) {
                return ToolResult::error('VALIDATION_ERROR', 'snooze_until muss in der Zukunft liegen.');
            }

            $signal->update(['snooze_until' => $snoozeUntil]);

            // Create system comment
            OrganizationSignalComment::create([
                'signal_id' => $signalId,
                'user_id' => $context->user?->id,
                'author_context' => 'system',
                'content' => 'Wiedervorlage: ' . $snoozeUntil->format('d.m.Y H:i'),
            ]);

            return ToolResult::success([
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'snooze_until' => $snoozeUntil->toIso8601String(),
                'message' => 'Signal auf Wiedervorlage gesetzt bis ' . $snoozeUntil->format('d.m.Y H:i') . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Snoozen des Signals: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'snooze', 'workflow'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimePeriod;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\StorePlannedPeriod;

class CreatePlannedPeriodTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.planned_period.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/planned-period - Erstellt einen Soll-Zeitraum (geplanter Start-/Endzeitraum). Kontext (context_type + context_id) ist erforderlich. Mindestens planned_start oder planned_end muss gesetzt sein. Erlaubte context_type Kurzformen: ' . implode(', ', ContextTypeRegistry::shortNames()) . '.';
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
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: User-ID für den der Eintrag erstellt wird. Default: aktueller User.',
                ],
                'planned_start' => [
                    'type' => 'string',
                    'description' => 'Optional: Geplanter Start (YYYY-MM-DD). Mindestens planned_start oder planned_end muss gesetzt sein.',
                ],
                'planned_end' => [
                    'type' => 'string',
                    'description' => 'Optional: Geplantes Ende (YYYY-MM-DD). Mindestens planned_start oder planned_end muss gesetzt sein.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung/Notiz zum Soll-Zeitraum.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Kontext-Typ (ERFORDERLICH). Kurzformen: ' . implode(', ', array_map(fn($s) => '"'.$s.'"', ContextTypeRegistry::shortNames())) . '.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Kontext-Models (ERFORDERLICH).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ob der Eintrag aktiv ist. Default: true.',
                ],
            ],
            'required' => ['context_type', 'context_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $team = Team::find((int) $teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.');
            }

            $plannedStart = ($arguments['planned_start'] ?? null) ?: null;
            $plannedEnd = ($arguments['planned_end'] ?? null) ?: null;

            if (!$plannedStart && !$plannedEnd) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens planned_start oder planned_end muss gesetzt sein.');
            }

            $rawContextType = $arguments['context_type'] ?? null;
            $contextId = $arguments['context_id'] ?? null;

            $contextType = ContextTypeRegistry::resolve($rawContextType);

            if (!$contextType) {
                return ToolResult::error('VALIDATION_ERROR', "context_type ist erforderlich. Erlaubte Kurzformen: " . implode(', ', ContextTypeRegistry::shortNames()) . '.');
            }

            if (!$contextId) {
                return ToolResult::error('VALIDATION_ERROR', 'context_id ist erforderlich.');
            }

            if (!class_exists($contextType)) {
                return ToolResult::error('VALIDATION_ERROR', "context_type '{$contextType}' ist keine gültige Model-Klasse.");
            }

            $contextModel = $contextType::find((int) $contextId);
            if (!$contextModel) {
                return ToolResult::error('NOT_FOUND', "Kontext-Model ({$contextType}) mit ID {$contextId} nicht gefunden.");
            }

            $service = app(StorePlannedPeriod::class);
            $entry = $service->store([
                'team_id' => (int) $teamId,
                'user_id' => (int) ($arguments['user_id'] ?? $context->user->id),
                'context_type' => $contextType,
                'context_id' => (int) $contextId,
                'planned_start' => $plannedStart,
                'planned_end' => $plannedEnd,
                'note' => $arguments['note'] ?? null,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'team_id' => $entry->team_id,
                'user_id' => $entry->user_id,
                'planned_start' => $entry->planned_start?->toDateString(),
                'planned_end' => $entry->planned_end?->toDateString(),
                'context_type' => $entry->context_type,
                'context_id' => $entry->context_id,
                'note' => $entry->note,
                'is_active' => (bool) $entry->is_active,
                'message' => 'Soll-Zeitraum erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Soll-Zeitraums: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'planned_period', 'create', 'time_planning'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

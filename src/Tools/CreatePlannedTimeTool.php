<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\StorePlannedTime;

class CreatePlannedTimeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.planned_time.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/planned-time - Erstellt einen Soll-Zeiteintrag (geplante Zeit). Kontext (context_type + context_id) ist erforderlich. Erlaubte context_type Kurzformen: "project", "task", "ticket", "company".';
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
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Geplante Minuten (ERFORDERLICH). Beispiel: 480 für 8 Stunden.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung/Notiz zum Soll-Zeiteintrag.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Kontext-Typ (ERFORDERLICH). Kurzformen: "project", "task", "ticket", "company".',
                    'enum' => ['project', 'task', 'ticket', 'company'],
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Kontext-Models (ERFORDERLICH).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ob der Eintrag aktiv ist. Default: true.',
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig ab (YYYY-MM-DD). Null = unbefristet.',
                ],
                'valid_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig bis (YYYY-MM-DD). Null = unbefristet.',
                ],
            ],
            'required' => ['planned_minutes', 'context_type', 'context_id'],
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

            $minutes = $arguments['planned_minutes'] ?? null;
            if ($minutes === null || (int) $minutes < 1) {
                return ToolResult::error('VALIDATION_ERROR', 'planned_minutes ist erforderlich und muss mindestens 1 sein.');
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

            $validFrom = $arguments['valid_from'] ?? null;
            $validTo = $arguments['valid_to'] ?? null;

            if ($validFrom && $validTo && $validFrom > $validTo) {
                return ToolResult::error('VALIDATION_ERROR', 'valid_from muss vor oder gleich valid_to sein.');
            }

            $service = app(StorePlannedTime::class);
            $entry = $service->store([
                'team_id' => (int) $teamId,
                'user_id' => (int) ($arguments['user_id'] ?? $context->user->id),
                'context_type' => $contextType,
                'context_id' => (int) $contextId,
                'planned_minutes' => (int) $minutes,
                'note' => $arguments['note'] ?? null,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'valid_from' => $validFrom ?: null,
                'valid_to' => $validTo ?: null,
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'team_id' => $entry->team_id,
                'user_id' => $entry->user_id,
                'planned_minutes' => $entry->planned_minutes,
                'hours' => $entry->hours,
                'context_type' => $entry->context_type,
                'context_id' => $entry->context_id,
                'note' => $entry->note,
                'is_active' => (bool) $entry->is_active,
                'valid_from' => $entry->valid_from?->toDateString(),
                'valid_to' => $entry->valid_to?->toDateString(),
                'period_label' => $entry->period_label,
                'message' => "Soll-Zeiteintrag erfolgreich erstellt ({$entry->planned_minutes} Min / {$entry->hours}h).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Soll-Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'planned_time', 'create', 'time_planning'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

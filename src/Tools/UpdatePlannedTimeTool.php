<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\StorePlannedTime;

class UpdatePlannedTimeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.planned_time.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/planned-time/{id} - Aktualisiert einen Soll-Zeiteintrag (geplante Minuten, Notiz, Kontext, aktiv-Status). Parameter: planned_time_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'planned_time_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Soll-Zeiteintrags (ERFORDERLICH). Nutze organization.planned_time.GET um IDs zu finden.',
                ],
                'planned_minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue geplante Minuten.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung/Notiz ("" zum Leeren).',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Kontext-Typ. Kurzformen: "project", "task", "ticket", "company".',
                    'enum' => ['project', 'task', 'ticket', 'company'],
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Kontext-ID. Erforderlich wenn context_type gesetzt ist.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv-Status.',
                ],
            ],
            'required' => ['planned_time_id'],
        ]);
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

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'planned_time_id',
                OrganizationTimePlanned::class,
                'NOT_FOUND',
                'Soll-Zeiteintrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationTimePlanned $entry */
            $entry = $found['model'];

            if ((int) $entry->team_id !== (int) $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Soll-Zeiteintrag gehört nicht zum angegebenen Team.');
            }

            $updateData = [];

            if (array_key_exists('planned_minutes', $arguments)) {
                $m = (int) $arguments['planned_minutes'];
                if ($m < 1) {
                    return ToolResult::error('VALIDATION_ERROR', 'planned_minutes muss mindestens 1 sein.');
                }
                $updateData['planned_minutes'] = $m;
            }

            if (array_key_exists('note', $arguments)) {
                $n = (string) ($arguments['note'] ?? '');
                $updateData['note'] = $n === '' ? null : $n;
            }

            if (array_key_exists('is_active', $arguments)) {
                $updateData['is_active'] = (bool) $arguments['is_active'];
            }

            if (array_key_exists('context_type', $arguments)) {
                $rawContextType = $arguments['context_type'] ?? null;
                $newContextId = $arguments['context_id'] ?? null;

                if (empty($rawContextType)) {
                    return ToolResult::error('VALIDATION_ERROR', 'context_type darf nicht leer sein bei Soll-Zeiteinträgen.');
                }

                $newContextType = ContextTypeRegistry::resolve($rawContextType);
                if ($newContextType === null) {
                    return ToolResult::error('VALIDATION_ERROR', "context_type '{$rawContextType}' ist ungültig. Erlaubte Kurzformen: " . implode(', ', ContextTypeRegistry::shortNames()) . '.');
                }
                if (!$newContextId) {
                    return ToolResult::error('VALIDATION_ERROR', 'context_id ist erforderlich wenn context_type gesetzt ist.');
                }
                if (!class_exists($newContextType)) {
                    return ToolResult::error('VALIDATION_ERROR', "context_type '{$newContextType}' ist keine gültige Model-Klasse.");
                }
                $contextModel = $newContextType::find((int) $newContextId);
                if (!$contextModel) {
                    return ToolResult::error('NOT_FOUND', "Kontext-Model ({$newContextType}) mit ID {$newContextId} nicht gefunden.");
                }
                $updateData['context_type'] = $newContextType;
                $updateData['context_id'] = (int) $newContextId;
            }

            $service = app(StorePlannedTime::class);
            $entry = $service->update($entry, $updateData);

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
                'message' => 'Soll-Zeiteintrag erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Soll-Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'planned_time', 'update', 'time_planning'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

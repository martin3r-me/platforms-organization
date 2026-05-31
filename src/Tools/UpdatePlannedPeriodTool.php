<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationTimePeriod;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\StorePlannedPeriod;

class UpdatePlannedPeriodTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.planned_period.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/planned-period/{id} - Aktualisiert einen Soll-Zeitraum (geplanter Start/Ende, Notiz, Kontext, aktiv-Status). Parameter: planned_period_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'planned_period_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Soll-Zeitraums (ERFORDERLICH). Nutze organization.planned_period.GET um IDs zu finden.',
                ],
                'planned_start' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer geplanter Start (YYYY-MM-DD, "" zum Leeren).',
                ],
                'planned_end' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues geplantes Ende (YYYY-MM-DD, "" zum Leeren).',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung/Notiz ("" zum Leeren).',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Kontext-Typ. Kurzformen: ' . implode(', ', array_map(fn($s) => '"'.$s.'"', ContextTypeRegistry::shortNames())) . '.',
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
            'required' => ['planned_period_id'],
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
                'planned_period_id',
                OrganizationTimePeriod::class,
                'NOT_FOUND',
                'Soll-Zeitraum nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationTimePeriod $entry */
            $entry = $found['model'];

            if ((int) $entry->team_id !== (int) $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Soll-Zeitraum gehört nicht zum angegebenen Team.');
            }

            $updateData = [];

            if (array_key_exists('planned_start', $arguments)) {
                $val = (string) ($arguments['planned_start'] ?? '');
                $updateData['planned_start'] = $val === '' ? null : $val;
            }

            if (array_key_exists('planned_end', $arguments)) {
                $val = (string) ($arguments['planned_end'] ?? '');
                $updateData['planned_end'] = $val === '' ? null : $val;
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
                    return ToolResult::error('VALIDATION_ERROR', 'context_type darf nicht leer sein.');
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

            $service = app(StorePlannedPeriod::class);
            $entry = $service->update($entry, $updateData);

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
                'message' => 'Soll-Zeitraum erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Soll-Zeitraums: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'planned_period', 'update', 'time_planning'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

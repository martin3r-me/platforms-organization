<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\StoreTimeEntry;
use Platform\Organization\Services\TimeContextResolver;

class CreateTimeEntryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.time_entries.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/time-entries - Erstellt einen Zeiteintrag. Kontext (context_type + context_id) ist optional – ohne Kontext wird eine freie Zeiterfassung erstellt. Beispiel context_type: "Platform\Planner\Models\PlannerTask", "Platform\Planner\Models\PlannerProject".';
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
                'work_date' => [
                    'type' => 'string',
                    'description' => 'Arbeitsdatum im Format YYYY-MM-DD (ERFORDERLICH). Beispiel: "2025-01-15".',
                ],
                'minutes' => [
                    'type' => 'integer',
                    'description' => 'Dauer in Minuten (ERFORDERLICH). Beispiel: 90 für 1,5 Stunden.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung/Notiz zum Zeiteintrag.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Vollqualifizierter Model-Klassenname für den Kontext (morphTo). Beispiel: "Platform\\Planner\\Models\\PlannerTask". Ohne context_type wird eine freie Zeiterfassung erstellt.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Kontext-Models. Erforderlich wenn context_type gesetzt ist.',
                ],
                'rate_cents' => [
                    'type' => 'integer',
                    'description' => 'Optional: Stundensatz in Cent.',
                ],
                'is_billed' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist der Eintrag abgerechnet? Default: false.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['work_date', 'minutes'],
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

            // Validierung
            $workDate = trim((string) ($arguments['work_date'] ?? ''));
            if ($workDate === '') {
                return ToolResult::error('VALIDATION_ERROR', 'work_date ist erforderlich (Format: YYYY-MM-DD).');
            }

            $minutes = $arguments['minutes'] ?? null;
            if ($minutes === null || (int) $minutes < 1) {
                return ToolResult::error('VALIDATION_ERROR', 'minutes ist erforderlich und muss mindestens 1 sein.');
            }

            $contextType = $arguments['context_type'] ?? null;
            $contextId = $arguments['context_id'] ?? null;

            // Wenn context_type gesetzt, muss auch context_id gesetzt sein
            if ($contextType && !$contextId) {
                return ToolResult::error('VALIDATION_ERROR', 'context_id ist erforderlich wenn context_type gesetzt ist.');
            }

            // Kontext validieren wenn angegeben
            if ($contextType && $contextId) {
                if (!class_exists($contextType)) {
                    return ToolResult::error('VALIDATION_ERROR', "context_type '{$contextType}' ist keine gültige Model-Klasse.");
                }
                $contextModel = $contextType::find((int) $contextId);
                if (!$contextModel) {
                    return ToolResult::error('NOT_FOUND', "Kontext-Model ({$contextType}) mit ID {$contextId} nicht gefunden.");
                }
            }

            // Rate/Amount berechnen
            $rateCents = isset($arguments['rate_cents']) ? (int) $arguments['rate_cents'] : null;
            $amountCents = null;
            if ($rateCents !== null) {
                $amountCents = (int) round($rateCents * ((int) $minutes / 60));
            }

            // Mit oder ohne Kontext speichern
            if ($contextType && $contextId) {
                // Mit Kontext: StoreTimeEntry Service nutzen (Kontext-Kaskade)
                $service = app(StoreTimeEntry::class);
                $entry = $service->store([
                    'team_id' => (int) $teamId,
                    'user_id' => (int) ($arguments['user_id'] ?? $context->user->id),
                    'context_type' => $contextType,
                    'context_id' => (int) $contextId,
                    'work_date' => $workDate,
                    'minutes' => (int) $minutes,
                    'rate_cents' => $rateCents,
                    'amount_cents' => $amountCents,
                    'is_billed' => (bool) ($arguments['is_billed'] ?? false),
                    'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
                    'note' => $arguments['note'] ?? null,
                ]);
            } else {
                // Ohne Kontext: direkt erstellen (freie Zeiterfassung)
                $entry = OrganizationTimeEntry::create([
                    'team_id' => (int) $teamId,
                    'user_id' => (int) ($arguments['user_id'] ?? $context->user->id),
                    'context_type' => null,
                    'context_id' => null,
                    'root_context_type' => null,
                    'root_context_id' => null,
                    'work_date' => $workDate,
                    'minutes' => (int) $minutes,
                    'rate_cents' => $rateCents,
                    'amount_cents' => $amountCents,
                    'is_billed' => (bool) ($arguments['is_billed'] ?? false),
                    'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
                    'note' => $arguments['note'] ?? null,
                ]);
            }

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'team_id' => $entry->team_id,
                'user_id' => $entry->user_id,
                'work_date' => $entry->work_date->format('Y-m-d'),
                'minutes' => $entry->minutes,
                'hours' => $entry->hours,
                'formatted' => OrganizationTimeEntry::formatMinutes($entry->minutes),
                'context_type' => $entry->context_type,
                'context_id' => $entry->context_id,
                'root_context_type' => $entry->root_context_type,
                'root_context_id' => $entry->root_context_id,
                'note' => $entry->note,
                'is_billed' => (bool) $entry->is_billed,
                'message' => 'Zeiteintrag erfolgreich erstellt (' . OrganizationTimeEntry::formatMinutes($entry->minutes) . ').',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'time_entries', 'create', 'time_tracking'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

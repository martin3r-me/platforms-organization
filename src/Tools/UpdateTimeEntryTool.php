<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\TimeContextResolver;

class UpdateTimeEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.time_entries.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/time-entries/{id} - Aktualisiert einen Zeiteintrag (Dauer, Beschreibung, Datum, Kontext etc.). Parameter: time_entry_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'time_entry_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Zeiteintrags (ERFORDERLICH). Nutze organization.time_entries.GET um IDs zu finden.',
                ],
                'work_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Arbeitsdatum (YYYY-MM-DD).',
                ],
                'minutes' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Dauer in Minuten.',
                ],
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung/Notiz ("" zum Leeren).',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Kontext-Typ. Kurzformen: "project" (Planner-Projekt), "task" (Planner-Aufgabe), "ticket" (Helpdesk-Ticket), "company" (CRM-Firma). Alternativ vollqualifizierter Klassenname. Null/leer zum Entfernen des Kontexts.',
                    'enum' => ['project', 'task', 'ticket', 'company'],
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Kontext-ID. Erforderlich wenn context_type gesetzt ist.',
                ],
                'rate_cents' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer Stundensatz in Cent (0 zum Leeren).',
                ],
                'is_billed' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Abrechnungsstatus.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Neues Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['time_entry_id'],
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
                'time_entry_id',
                OrganizationTimeEntry::class,
                'NOT_FOUND',
                'Zeiteintrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationTimeEntry $entry */
            $entry = $found['model'];

            // Team-Zugehörigkeit prüfen
            if ((int) $entry->team_id !== (int) $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zeiteintrag gehört nicht zum angegebenen Team.');
            }

            $update = [];
            $contextChanged = false;

            if (array_key_exists('work_date', $arguments)) {
                $wd = trim((string) ($arguments['work_date'] ?? ''));
                if ($wd !== '') {
                    $update['work_date'] = $wd;
                }
            }

            if (array_key_exists('minutes', $arguments)) {
                $m = (int) $arguments['minutes'];
                if ($m < 1) {
                    return ToolResult::error('VALIDATION_ERROR', 'minutes muss mindestens 1 sein.');
                }
                $update['minutes'] = $m;
            }

            if (array_key_exists('note', $arguments)) {
                $n = (string) ($arguments['note'] ?? '');
                $update['note'] = $n === '' ? null : $n;
            }

            if (array_key_exists('rate_cents', $arguments)) {
                $rc = (int) $arguments['rate_cents'];
                $update['rate_cents'] = $rc === 0 ? null : $rc;
            }

            if (array_key_exists('is_billed', $arguments)) {
                $update['is_billed'] = (bool) $arguments['is_billed'];
            }

            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            // Kontext-Änderung
            if (array_key_exists('context_type', $arguments)) {
                $rawContextType = $arguments['context_type'] ?? null;
                $newContextId = $arguments['context_id'] ?? null;

                if (empty($rawContextType) || $rawContextType === 'null' || $rawContextType === '') {
                    // Kontext entfernen
                    $update['context_type'] = null;
                    $update['context_id'] = null;
                    $update['root_context_type'] = null;
                    $update['root_context_id'] = null;
                    $update['has_key_result'] = false;
                    $contextChanged = true;
                } else {
                    // Kurzform auflösen
                    $newContextType = ContextTypeRegistry::resolve($rawContextType);
                    if ($newContextType === null) {
                        return ToolResult::error('VALIDATION_ERROR', "context_type '{$rawContextType}' ist ungültig. Erlaubte Kurzformen: " . implode(', ', ContextTypeRegistry::shortNames()) . '. Nutze organization.lookups.GET (lookup="context_types") für alle erlaubten Werte.');
                    }
                    if (!$newContextId) {
                        return ToolResult::error('VALIDATION_ERROR', 'context_id ist erforderlich wenn context_type gesetzt ist.');
                    }
                    if (!class_exists($newContextType)) {
                        return ToolResult::error('VALIDATION_ERROR', "context_type '{$newContextType}' ist keine gültige Model-Klasse. Erlaubte Kurzformen: " . implode(', ', ContextTypeRegistry::shortNames()) . '.');
                    }
                    $contextModel = $newContextType::find((int) $newContextId);
                    if (!$contextModel) {
                        return ToolResult::error('NOT_FOUND', "Kontext-Model ({$newContextType}) mit ID {$newContextId} nicht gefunden.");
                    }
                    $update['context_type'] = $newContextType;
                    $update['context_id'] = (int) $newContextId;
                    $contextChanged = true;
                }
            }

            // Amount neu berechnen wenn rate_cents oder minutes geändert
            $finalMinutes = $update['minutes'] ?? $entry->minutes;
            $finalRate = array_key_exists('rate_cents', $update) ? $update['rate_cents'] : $entry->rate_cents;
            if ($finalRate) {
                $update['amount_cents'] = (int) round($finalRate * ($finalMinutes / 60));
            } elseif (array_key_exists('rate_cents', $update) && $update['rate_cents'] === null) {
                $update['amount_cents'] = null;
            }

            if (!empty($update)) {
                $entry->update($update);
            }

            // Wenn Kontext geändert: alte Contexts löschen und neu aufbauen
            if ($contextChanged && $entry->context_type && $entry->context_id) {
                $entry->additionalContexts()->delete();
                // Kontext-Kaskade neu aufbauen über StoreTimeEntry-Logik
                $resolver = app(TimeContextResolver::class);
                $ancestors = $resolver->resolveAncestors($entry->context_type, $entry->context_id);

                $hasAncestors = !empty($ancestors);
                $isPrimaryRoot = !$hasAncestors;

                $primaryLabel = $resolver->resolveLabel($entry->context_type, $entry->context_id);
                \Platform\Organization\Models\OrganizationTimeEntryContext::updateOrCreate(
                    [
                        'time_entry_id' => $entry->id,
                        'context_type' => $entry->context_type,
                        'context_id' => $entry->context_id,
                    ],
                    [
                        'depth' => 0,
                        'is_primary' => true,
                        'is_root' => $isPrimaryRoot,
                        'context_label' => $primaryLabel,
                    ]
                );

                $firstRoot = null;
                foreach ($ancestors as $depth => $ancestor) {
                    $isRoot = $ancestor['is_root'] ?? false;
                    if ($firstRoot === null && $isRoot) {
                        $firstRoot = ['type' => $ancestor['type'], 'id' => $ancestor['id']];
                    }
                    \Platform\Organization\Models\OrganizationTimeEntryContext::updateOrCreate(
                        [
                            'time_entry_id' => $entry->id,
                            'context_type' => $ancestor['type'],
                            'context_id' => $ancestor['id'],
                        ],
                        [
                            'depth' => $depth + 1,
                            'is_primary' => false,
                            'is_root' => $isRoot,
                            'context_label' => $ancestor['label'] ?? $resolver->resolveLabel($ancestor['type'], $ancestor['id']),
                        ]
                    );
                }

                $rootType = $firstRoot ? $firstRoot['type'] : $entry->context_type;
                $rootId = $firstRoot ? $firstRoot['id'] : $entry->context_id;
                $entry->update([
                    'root_context_type' => $rootType,
                    'root_context_id' => $rootId,
                ]);
            } elseif ($contextChanged && !$entry->context_type) {
                // Kontext wurde entfernt
                $entry->additionalContexts()->delete();
            }

            $entry->refresh();

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
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
                'rate_cents' => $entry->rate_cents,
                'amount_cents' => $entry->amount_cents,
                'message' => 'Zeiteintrag erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Zeiteintrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'time_entries', 'update', 'time_tracking'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

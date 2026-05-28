<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class AcknowledgeSignalTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.acknowledge';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signals/{id}/acknowledge - Ändert den Status eines Signals (acknowledge, resolve, dismiss). Nutze organization.signals.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'signal_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Signals.',
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: acknowledge, resolve oder dismiss.',
                    'enum' => ['acknowledge', 'resolve', 'dismiss'],
                ],
            ],
            'required' => ['signal_id', 'action'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'signal_id',
                OrganizationSignal::class,
                'NOT_FOUND',
                'Signal nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSignal $signal */
            $signal = $found['model'];

            if ((int) $signal->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Signal gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $action = $arguments['action'] ?? '';
            if (! in_array($action, ['acknowledge', 'resolve', 'dismiss'])) {
                return ToolResult::error('VALIDATION_ERROR', 'action muss acknowledge, resolve oder dismiss sein.');
            }

            $update = match ($action) {
                'acknowledge' => ['status' => 'acknowledged'],
                'resolve' => [
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by' => $context->user?->id,
                ],
                'dismiss' => ['status' => 'dismissed'],
            };

            $signal->update($update);

            $statusLabels = [
                'acknowledge' => 'bestätigt',
                'resolve' => 'gelöst',
                'dismiss' => 'verworfen',
            ];

            return ToolResult::success([
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'status' => $signal->status,
                'message' => 'Signal ' . $statusLabels[$action] . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Signals: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'algedonic', 'acknowledge'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

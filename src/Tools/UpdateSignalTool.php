<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSignalTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/signals/{id} - Reassignment eines Signals. Aktuell nur assignee_entity_id (0/null zum Entfernen). Für Statuswechsel (acknowledge/resolve/dismiss) nutze organization.signals.acknowledge.';
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
                'assignee_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Neue Assignee-Entity (Person- oder Gremien-Entity). 0/null zum Entfernen.',
                ],
            ],
            'required' => ['signal_id'],
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

            if (! array_key_exists('assignee_entity_id', $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein aktualisierbares Feld angegeben (assignee_entity_id).');
            }

            $update = [];
            $val = $arguments['assignee_entity_id'];
            if ($val === null || $val === '' || $val === 'null' || $val === 0 || $val === '0') {
                $update['assignee_entity_id'] = null;
            } else {
                $assigneeEntityId = (int) $val;
                $assignee = OrganizationEntity::where('id', $assigneeEntityId)
                    ->where('team_id', $rootTeamId)
                    ->first();
                if (! $assignee) {
                    return ToolResult::error('NOT_FOUND', 'Assignee-Entity nicht gefunden oder gehört nicht zum selben Team.');
                }
                $update['assignee_entity_id'] = $assigneeEntityId;
            }

            $signal->update($update);

            return ToolResult::success([
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'assignee_entity_id' => $signal->assignee_entity_id,
                'assignee_name' => $signal->assignee?->name,
                'message' => 'Signal-Zuordnung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Signals: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'algedonic', 'reassign', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

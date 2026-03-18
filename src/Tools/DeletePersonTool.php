<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationPerson;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeletePersonTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.persons.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/persons/{id} - Löscht eine Person (soft delete). Hinweis: Wenn die Person verlinkt ist, bitte is_active=false setzen statt löschen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'person_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Person (ERFORDERLICH). Nutze organization.persons.GET.',
                ],
            ],
            'required' => ['person_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'person_id',
                OrganizationPerson::class,
                'NOT_FOUND',
                'Person nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationPerson $person */
            $person = $found['model'];
            if ((int)$person->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Person gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            if (method_exists($person, 'links') && $person->links()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Person ist verlinkt und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $person->delete();

            return ToolResult::success([
                'id' => $person->id,
                'message' => 'Person gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Person: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'persons', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

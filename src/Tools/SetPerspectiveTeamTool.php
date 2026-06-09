<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationPerspectiveTeam;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Ordnet einer Carrier-Perspektive ein Plattform-Team zu.
 * is_default=true setzt sie als Standard-Perspektive des Teams
 * und raeumt andere Defaults im selben Team ab (via PerspectiveService).
 */
class SetPerspectiveTeamTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.perspective_teams.SET';
    }

    public function getDescription(): string
    {
        return 'POST /organization/perspective-teams - Ordnet einer Carrier-Perspektive ein Plattform-Team zu (M:N). Mit is_default=true wird die Perspektive zum Standard fuer Mitglieder, die das Org-Modul nie oeffnen (z.B. Algedonic-Routing).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID fuer den Tool-Kontext (Berechtigung). Default: Team aus Kontext.',
                ],
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Carrier-Entity, die als Perspektive dienen soll.',
                ],
                'mapped_team_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Plattform-Teams, das diese Perspektive nutzen soll.',
                ],
                'is_default' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, wird diese Perspektive Standard-Perspektive des mapped_team_id (andere Defaults werden abgeraeumt). Default: false.',
                    'default' => false,
                ],
            ],
            'required' => ['perspective_entity_id', 'mapped_team_id'],
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

            $perspectiveEntityId = (int) ($arguments['perspective_entity_id'] ?? 0);
            $mappedTeamId = (int) ($arguments['mapped_team_id'] ?? 0);
            $isDefault = (bool) ($arguments['is_default'] ?? false);

            if ($perspectiveEntityId <= 0 || $mappedTeamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'perspective_entity_id und mapped_team_id sind erforderlich.');
            }

            $entity = OrganizationEntity::with('type')->find($perspectiveEntityId);
            if (! $entity) {
                return ToolResult::error('NOT_FOUND', 'Perspektive-Entity nicht gefunden.');
            }
            if ((int) $entity->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Perspektive-Entity gehoert nicht zum Root-Team des Tool-Kontexts.');
            }
            if ($entity->type?->vsm_class !== OrganizationEntityType::VSM_CLASS_CARRIER) {
                return ToolResult::error('VALIDATION_ERROR', 'Perspektive-Entity muss vsm_class=carrier sein.');
            }

            $team = Team::find($mappedTeamId);
            if (! $team) {
                return ToolResult::error('NOT_FOUND', 'mapped_team_id nicht gefunden.');
            }

            if ($isDefault) {
                $pt = PerspectiveService::setTeamDefault($perspectiveEntityId, $mappedTeamId);
            } else {
                $pt = OrganizationPerspectiveTeam::updateOrCreate(
                    ['perspective_entity_id' => $perspectiveEntityId, 'team_id' => $mappedTeamId],
                    [], // is_default bleibt bei einem bestehenden Eintrag unveraendert
                );
            }

            if (! $pt) {
                return ToolResult::error('EXECUTION_ERROR', 'Mapping konnte nicht angelegt werden.');
            }

            return ToolResult::success([
                'id' => $pt->id,
                'perspective_entity_id' => $pt->perspective_entity_id,
                'perspective_entity_name' => $entity->name,
                'mapped_team_id' => $pt->team_id,
                'mapped_team_name' => $team->name,
                'is_default' => (bool) $pt->is_default,
                'message' => $isDefault
                    ? 'Perspektive als Standard fuer Team gesetzt.'
                    : 'Perspektive dem Team zugeordnet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Setzen des Mappings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'perspective', 'vsm', 'team', 'mapping'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

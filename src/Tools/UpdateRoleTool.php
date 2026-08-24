<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateRoleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.roles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/roles/{id} - Aktualisiert eine Rolle.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer'],
                'role_id'     => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'name'        => ['type' => 'string'],
                'slug'        => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'vsm_system'  => ['type' => 'string', 'description' => 'Optional: s1, s2, s3, s3_star, s4, s5. "" zum Loesen.', 'enum' => ['', 's1', 's2', 's3', 's3_star', 's4', 's5']],
                'domain'      => ['type' => 'string', 'description' => 'Optional: macht die Rolle AGENT-ausfuehrbar (development|backoffice|accounting|helpdesk|assistant|analysis). "" zum Loesen (reine Menschen-Rolle).'],
                'stage'       => ['type' => 'string', 'description' => 'Optional (mit domain): triage|execute|learn|signal. "" zum Loesen.'],
                'capabilities' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['read', 'write', 'manage']], 'description' => 'Optional: Content-Zugriff der Rolle auf Kontext-Entity + Teilbaum (read|write|manage; hoechste gewinnt). Leeres Array [] = KEIN Zugriff. Fuer arbeitende Agenten i.d.R. ["write"].'],
                'status'      => ['type' => 'string'],
            ],
            'required' => ['role_id'],
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
                'role_id',
                OrganizationRole::class,
                'NOT_FOUND',
                'Rolle nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationRole $role */
            $role = $found['model'];
            if ((int) $role->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Rolle gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $val;
            }
            if (array_key_exists('slug', $arguments)) {
                $val = trim((string) ($arguments['slug'] ?? ''));
                if ($val !== '') {
                    $exists = OrganizationRole::query()
                        ->where('team_id', $rootTeamId)
                        ->where('slug', $val)
                        ->where('id', '!=', $role->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', "Rolle mit slug '{$val}' existiert bereits.");
                    }
                    $update['slug'] = $val;
                }
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('vsm_system', $arguments)) {
                $val = (string) ($arguments['vsm_system'] ?? '');
                if ($val === '') {
                    $update['vsm_system'] = null;
                } elseif (in_array($val, OrganizationRole::VSM_SYSTEMS, true)) {
                    $update['vsm_system'] = $val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'vsm_system muss einer von ' . implode(', ', OrganizationRole::VSM_SYSTEMS) . ' oder "" sein.');
                }
            }
            if (array_key_exists('domain', $arguments)) {
                $val = (string) ($arguments['domain'] ?? '');
                if ($val === '') {
                    $update['domain'] = null;
                } elseif (in_array($val, OrganizationRole::DOMAINS, true)) {
                    $update['domain'] = $val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'domain muss einer von ' . implode(', ', OrganizationRole::DOMAINS) . ' oder "" sein.');
                }
            }
            if (array_key_exists('stage', $arguments)) {
                $val = (string) ($arguments['stage'] ?? '');
                if ($val === '') {
                    $update['stage'] = null;
                } elseif (in_array($val, OrganizationRole::STAGES, true)) {
                    $update['stage'] = $val;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'stage muss einer von ' . implode(', ', OrganizationRole::STAGES) . ' oder "" sein.');
                }
            }
            if (array_key_exists('capabilities', $arguments)) {
                $caps = $arguments['capabilities'];
                if (! is_array($caps)) {
                    return ToolResult::error('VALIDATION_ERROR', 'capabilities muss ein Array sein (read|write|manage) oder [] zum Loesen.');
                }
                foreach ($caps as $c) {
                    if (! in_array($c, ['read', 'write', 'manage'], true)) {
                        return ToolResult::error('VALIDATION_ERROR', 'capabilities dürfen nur read|write|manage enthalten.');
                    }
                }
                $update['capabilities'] = array_values(array_unique($caps)) ?: null;
            }
            if (array_key_exists('status', $arguments)) {
                $update['status'] = (string) $arguments['status'];
            }

            if (! empty($update)) {
                $role->update($update);
            }
            $role->refresh();

            return ToolResult::success([
                'id'         => $role->id,
                'name'       => $role->name,
                'slug'       => $role->slug,
                'status'     => $role->status,
                'vsm_system' => $role->vsm_system,
                'domain'     => $role->domain,
                'stage'      => $role->stage,
                'capabilities' => $role->capabilities,
                'team_id'    => $role->team_id,
                'message'    => 'Rolle erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Rolle: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'roles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}

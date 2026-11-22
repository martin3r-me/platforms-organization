<?php

namespace Platform\Organization\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationDeputy;
use Platform\Organization\Models\OrganizationEntity;

/**
 * organization.deputies.GET | POST | DELETE – Vertretungen. Anlegen/Löschen darf die vertretene Person selbst
 * oder wer an ihrem Knoten Schreibrecht hat. Die konkreten Tools setzen nur das Verb.
 */
abstract class DeputyToolBase implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    protected string $verb = 'GET';

    public function getName(): string
    {
        return 'organization.deputies.' . $this->verb;
    }

    public function getDescription(): string
    {
        return match ($this->verb) {
            'GET' => 'GET /organization/deputies - Vertretungen (wer vertritt wen, wo, ab wann). Filter: principal_entity_id, deputy_entity_id, include_expired.',
            'POST' => 'POST /organization/deputies - Vertretung anlegen. Pflicht: principal_entity_id (wer vertreten wird), deputy_entity_id (wer vertritt). '
                . 'Optional: scope_entity_id (nur für diesen Knoten und darunter), only_when_absent (Standard true; false = Vertreter ist immer mit verantwortlich), valid_from, valid_to, note.',
            'DELETE' => 'DELETE /organization/deputies/{id} - Vertretung beenden.',
        };
    }

    public function getSchema(): array
    {
        return match ($this->verb) {
            'GET' => ['type' => 'object', 'properties' => ['principal_entity_id' => ['type' => 'integer'], 'deputy_entity_id' => ['type' => 'integer'], 'include_expired' => ['type' => 'boolean'], 'team_id' => ['type' => 'integer']]],
            'POST' => ['type' => 'object', 'properties' => [
                'principal_entity_id' => ['type' => 'integer'], 'deputy_entity_id' => ['type' => 'integer'], 'scope_entity_id' => ['type' => 'integer'],
                'only_when_absent' => ['type' => 'boolean'], 'valid_from' => ['type' => 'string'], 'valid_to' => ['type' => 'string'], 'note' => ['type' => 'string'], 'team_id' => ['type' => 'integer'],
            ], 'required' => ['principal_entity_id', 'deputy_entity_id']],
            'DELETE' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'team_id' => ['type' => 'integer']], 'required' => ['id']],
        };
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamIds = array_values(array_unique(array_filter([(int) $resolved['team_id'], (int) $resolved['root_team_id']])));
            $q = OrganizationDeputy::query()->whereIn('team_id', $teamIds)->with(['principal:id,name', 'deputy:id,name', 'scope:id,name']);

            if ($this->verb === 'GET') {
                foreach (['principal_entity_id', 'deputy_entity_id'] as $f) {
                    if (! empty($arguments[$f])) {
                        $q->where($f, (int) $arguments[$f]);
                    }
                }
                if (empty($arguments['include_expired'])) {
                    $q->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()));
                }

                return ToolResult::success(['deputies' => $q->orderBy('principal_entity_id')->get()->map(fn ($d) => $this->toArray($d))->all()]);
            }

            if ($this->verb === 'POST') {
                $p = (int) ($arguments['principal_entity_id'] ?? 0);
                $d = (int) ($arguments['deputy_entity_id'] ?? 0);
                if (! $p || ! $d || $p === $d) {
                    return ToolResult::error('VALIDATION_ERROR', 'principal_entity_id und deputy_entity_id sind erforderlich und verschieden.');
                }
                $actors = OrganizationEntity::query()->with('type:id,code')->whereIn('id', [$p, $d])->get()->keyBy('id');
                foreach ([$p, $d] as $id) {
                    if (! isset($actors[$id]) || ! in_array($actors[$id]->type?->code, ['person', 'agent'], true)) {
                        return ToolResult::error('VALIDATION_ERROR', "Knoten {$id} ist keine Person und kein Agent.");
                    }
                }
                if (! $this->mayEdit($context, $actors[$p])) {
                    return ToolResult::error('ACCESS_DENIED', 'Vertretung anlegen darf die vertretene Person selbst oder wer an ihrem Knoten Schreibrecht hat.');
                }
                $dep = OrganizationDeputy::create([
                    'team_id' => $actors[$p]->team_id, 'principal_entity_id' => $p, 'deputy_entity_id' => $d,
                    'scope_entity_id' => ! empty($arguments['scope_entity_id']) ? (int) $arguments['scope_entity_id'] : null,
                    'only_when_absent' => array_key_exists('only_when_absent', $arguments) ? (bool) $arguments['only_when_absent'] : true,
                    'valid_from' => $arguments['valid_from'] ?? null, 'valid_to' => $arguments['valid_to'] ?? null,
                    'note' => $arguments['note'] ?? null, 'created_by_user_id' => $context->user->id,
                ]);

                return ToolResult::success(['deputy' => $this->toArray($dep->fresh(['principal:id,name', 'deputy:id,name', 'scope:id,name']))]);
            }

            $dep = $q->find((int) ($arguments['id'] ?? 0));
            if (! $dep) {
                return ToolResult::error('NOT_FOUND', 'Vertretung nicht gefunden.');
            }
            if (! $this->mayEdit($context, OrganizationEntity::query()->find($dep->principal_entity_id))) {
                return ToolResult::error('ACCESS_DENIED', 'Keine Berechtigung für diese Vertretung.');
            }
            $dep->delete();

            return ToolResult::success(['id' => $dep->id, 'message' => 'Vertretung beendet.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function mayEdit(ToolContext $context, ?OrganizationEntity $principal): bool
    {
        if (! $principal) {
            return false;
        }
        if ((int) $principal->linked_user_id === (int) $context->user->id) {
            return true;
        }

        return in_array($principal->id, (new DimensionLinkAuthorizer())->writableEntityIds($context->user), true);
    }

    private function toArray(OrganizationDeputy $d): array
    {
        return [
            'id' => $d->id, 'principal' => ['entity_id' => $d->principal_entity_id, 'name' => $d->principal?->name],
            'deputy' => ['entity_id' => $d->deputy_entity_id, 'name' => $d->deputy?->name],
            'scope' => $d->scope_entity_id ? ['entity_id' => $d->scope_entity_id, 'name' => $d->scope?->name] : null,
            'only_when_absent' => $d->only_when_absent, 'valid_from' => $d->valid_from?->toDateString(), 'valid_to' => $d->valid_to?->toDateString(), 'note' => $d->note,
        ];
    }

    public function getMetadata(): array
    {
        $ro = $this->verb === 'GET';

        return ['category' => $ro ? 'read' : 'action', 'tags' => ['organization', 'deputy', 'vertretung', 'responsibility'], 'read_only' => $ro,
            'requires_auth' => true, 'requires_team' => true, 'risk_level' => $ro ? 'safe' : 'write', 'idempotent' => $ro];
    }
}

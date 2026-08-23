<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Legt das agent_profile einer Agent-Entity an bzw. aktualisiert es (an/aus + Governor + Claim-Cap
 * + Modell). Ohne Profil liefert der /agent/profile-Endpoint 404 → der Daemon kann nicht starten.
 * Macht das Agent-Onboarding komplett API-getrieben (statt Klick im Agent-Tab). Die DOMÄNE kommt
 * NICHT von hier, sondern aus den Rollen-Assignments (organization.role_assignments.POST mit einer
 * Rolle, die domain/stage traegt).
 */
class UpsertAgentProfileTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.agent_profile.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/agent-profile - Legt das Agent-Profil einer Agent-Entity an/aktualisiert es (active, Governor-Reserven, Claim-Cap, Claude-Modell, claim_unassigned, github_username). Nur fuer Entities vom Typ "agent". Domaene kommt aus den Rollen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'                   => ['type' => 'integer'],
                'entity_id'                 => ['type' => 'integer', 'description' => 'ERFORDERLICH: die Agent-Entity (Typ agent).'],
                'active'                    => ['type' => 'boolean', 'description' => 'an/aus — der Daemon liest es (Default true bei Neuanlage).'],
                'five_hour_reserve_pct'     => ['type' => 'integer', 'description' => '0-100. Puffer im 5h-Fenster (Default 90).'],
                'seven_day_burn_margin_pct' => ['type' => 'integer', 'description' => '0-100 (Default 10).'],
                'max_story_points'          => ['type' => 'integer', 'description' => 'Claim-Cap (null/0 = kein Cap).'],
                'claude_model'              => ['type' => 'string', 'description' => 'optional; leer = bestes verfuegbares.'],
                'claim_unassigned'          => ['type' => 'boolean', 'description' => 'auch herrenlose Pool-Aufgaben claimen (Default true).'],
                'github_username'           => ['type' => 'string', 'description' => 'nur Referenz (kein Token).'],
            ],
            'required' => ['entity_id'],
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

            $entityId = (int) ($arguments['entity_id'] ?? 0);
            $entity = OrganizationEntity::query()
                ->where('id', $entityId)
                ->where('team_id', $rootTeamId)
                ->with('type')
                ->first();
            if (! $entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden (oder nicht im Root/Elterteam).');
            }
            if ($entity->type?->code !== 'agent') {
                return ToolResult::error('VALIDATION_ERROR', 'Entity ist kein Agent (Typ "agent" erforderlich). Vorher umschluesseln via organization.entities.PUT (entity_type_id des Agent-Typs).');
            }

            // Nur uebergebene Felder setzen; Defaults nur bei Neuanlage.
            $exists = $entity->agentProfile()->exists();
            $fields = [];
            foreach (['five_hour_reserve_pct', 'seven_day_burn_margin_pct'] as $k) {
                if (array_key_exists($k, $arguments)) {
                    $v = (int) $arguments[$k];
                    if ($v < 0 || $v > 100) {
                        return ToolResult::error('VALIDATION_ERROR', "$k muss 0-100 sein.");
                    }
                    $fields[$k] = $v;
                }
            }
            if (array_key_exists('active', $arguments)) {
                $fields['active'] = (bool) $arguments['active'];
            }
            if (array_key_exists('claim_unassigned', $arguments)) {
                $fields['claim_unassigned'] = (bool) $arguments['claim_unassigned'];
            }
            if (array_key_exists('max_story_points', $arguments)) {
                $fields['max_story_points'] = ((int) $arguments['max_story_points']) ?: null;
            }
            if (array_key_exists('claude_model', $arguments)) {
                $fields['claude_model'] = ((string) $arguments['claude_model']) ?: null;
            }
            if (array_key_exists('github_username', $arguments)) {
                $fields['github_username'] = ((string) $arguments['github_username']) ?: null;
            }
            if (! $exists && ! array_key_exists('active', $arguments)) {
                $fields['active'] = true; // sinnvoller Default bei Neuanlage
            }

            $profile = $entity->agentProfile()->updateOrCreate([], $fields);

            return ToolResult::success([
                'entity_id'                 => $entity->id,
                'created'                   => ! $exists,
                'active'                    => (bool) $profile->active,
                'five_hour_reserve_pct'     => $profile->five_hour_reserve_pct,
                'seven_day_burn_margin_pct' => $profile->seven_day_burn_margin_pct,
                'max_story_points'          => $profile->max_story_points,
                'claude_model'              => $profile->claude_model,
                'claim_unassigned'          => (bool) $profile->claim_unassigned,
                'github_username'           => $profile->github_username,
                'message'                   => $exists ? 'Agent-Profil aktualisiert.' : 'Agent-Profil angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Setzen des Agent-Profils: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'agent', 'profile', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}

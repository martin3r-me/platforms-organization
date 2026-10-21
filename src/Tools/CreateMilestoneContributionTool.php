<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestone;
use Platform\Organization\Models\OrganizationMilestoneContribution;
use Platform\Organization\Services\ObjectTypeRegistry;

class CreateMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestone_contributions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/milestone-contributions - Ein Objekt (typisch Objective oder Key Result) zahlt auf einen Meilenstein '
            . 'der Transformations-Map ein (Ausrichtung Umsetzung → Strategie). linkable_type = registrierter Objekttyp '
            . '(Kurzname, Morph-Alias oder Klasse). Idempotent: existiert die Verknuepfung, wird nur weight aktualisiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'milestone_id'  => ['type' => 'integer', 'description' => 'Meilenstein-ID (PFLICHT).'],
                'linkable_type' => ['type' => 'string', 'description' => 'Objekttyp (PFLICHT), z. B. der Morph-Alias eines Objectives oder Key Results.'],
                'linkable_id'   => ['type' => 'integer', 'description' => 'ID des Objekts (PFLICHT).'],
                'weight'        => ['type' => 'integer', 'description' => 'Optional 1-100: Anteil am Meilenstein (fuer spaetere Fortschritts-Hochrechnung).'],
            ],
            'required' => ['milestone_id', 'linkable_type', 'linkable_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $milestoneId = (int) ($arguments['milestone_id'] ?? 0);
            $type = (string) ($arguments['linkable_type'] ?? '');
            $linkableId = (int) ($arguments['linkable_id'] ?? 0);
            if ($milestoneId <= 0 || $linkableId <= 0 || $type === '') {
                return ToolResult::error('VALIDATION_ERROR', 'milestone_id, linkable_type und linkable_id sind erforderlich.');
            }
            $milestone = OrganizationMilestone::find($milestoneId);
            if (!$milestone) {
                return ToolResult::error('NOT_FOUND', "Meilenstein #{$milestoneId} nicht gefunden.");
            }

            // Typ vorab normalisieren, damit Kurzname/Alias/Klasse auf denselben Datensatz treffen (Idempotenz).
            $alias = ObjectTypeRegistry::resolve($type);
            if (!$alias) {
                return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$type}' ist nicht registriert.");
            }
            $c = OrganizationMilestoneContribution::firstOrNew([
                'milestone_id'  => $milestoneId,
                'linkable_type' => $alias,
                'linkable_id'   => $linkableId,
            ]);
            $created = !$c->exists;
            if (array_key_exists('weight', $arguments)) {
                $c->weight = $arguments['weight'] !== null ? (int) $arguments['weight'] : null;
            }
            if ($created) {
                $c->team_id = $milestone->team_id;
                $c->created_by_user_id = $context->user?->id;
            }
            $c->save();
            $c->load('milestone.focusArea', 'milestone.entity');

            return ToolResult::success(array_merge($c->toAlignmentArray(), [
                'linkable_type' => $c->linkable_type,
                'linkable_id'   => $c->linkable_id,
                'created'       => $created,
                'message'       => $created ? 'Beitrag zum Meilenstein angelegt.' : 'Beitrag existierte bereits — aktualisiert.',
            ]));
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'contributions', 'okr', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}

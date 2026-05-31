<?php

namespace Platform\Organization\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Services\DimensionLinkService;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class LinkEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    /**
     * Friendly short names → morph alias.
     */
    protected const ALIAS_MAP = [
        'project' => 'project', 'planner_project' => 'project',
        'task' => 'planner_task', 'planner_task' => 'planner_task',
        'package' => 'dev_package', 'dev_package' => 'dev_package',
        'issue' => 'dev_issue', 'dev_issue' => 'dev_issue',
        'okr' => 'okr',
        'canvas' => 'canvas', 'bmc_canvas' => 'bmc_canvas', 'pc_canvas' => 'pc_canvas',
        'contact' => 'crm_contact', 'crm_contact' => 'crm_contact',
        'company' => 'crm_company', 'crm_company' => 'crm_company',
        'ticket' => 'helpdesk_ticket', 'helpdesk_ticket' => 'helpdesk_ticket',
        'helpdesk_board' => 'helpdesk_board',
        'employee' => 'hcm_employee', 'hcm_employee' => 'hcm_employee',
        'thread' => 'correspondence_thread', 'correspondence_thread' => 'correspondence_thread',
        'recording' => 'whisper_recording', 'whisper_recording' => 'whisper_recording',
        'note' => 'notes_note', 'notes_note' => 'notes_note',
        'spreadsheet' => 'sheets_spreadsheet', 'sheets_spreadsheet' => 'sheets_spreadsheet',
        'presentation' => 'slides_presentation', 'slides_presentation' => 'slides_presentation',
        'applicant' => 'rec_applicant', 'rec_applicant' => 'rec_applicant',
        'position' => 'rec_position', 'rec_position' => 'rec_position',
        'process' => 'organization_process', 'organization_process' => 'organization_process',
        'change_project' => 'change_project',
        'bank_account_group' => 'drip_bank_account_group', 'drip_bank_account_group' => 'drip_bank_account_group',
        'bank_transaction' => 'drip_bank_transaction', 'drip_bank_transaction' => 'drip_bank_transaction',
        'brand' => 'brands_brand', 'brands_brand' => 'brands_brand',
        'seo_url' => 'seo_url', 'seo_url_list' => 'seo_url_list',
    ];

    public function getName(): string
    {
        return 'organization.entity.link.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entity/link - Verknüpft ein Objekt (Projekt, Package, Ticket, Task etc.) mit einer Entity. Einfachste Variante: entity_id + object_type + object_id. Akzeptiert kurze Namen wie "project", "package", "ticket". Beispiel: entity_id=85, object_type="package", object_id=42 → Package #42 hängt an Entity #85.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Ziel-Entity (Organisationseinheit an die das Objekt gehängt wird).',
                ],
                'object_type' => [
                    'type' => 'string',
                    'description' => 'Typ des Objekts. Kurze Namen: project, package, task, issue, ticket, canvas, thread, contact, okr, note, employee, applicant, brand, process, recording, spreadsheet, presentation.',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Objekts das verknüpft werden soll.',
                ],
            ],
            'required' => ['entity_id', 'object_type', 'object_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }

            $entityId = (int) $arguments['entity_id'];
            $objectType = $arguments['object_type'];
            $objectId = (int) $arguments['object_id'];

            // Resolve friendly name → morph alias
            $morphAlias = self::ALIAS_MAP[$objectType] ?? null;

            if (!$morphAlias && Relation::getMorphedModel($objectType)) {
                $morphAlias = $objectType;
            }

            if (!$morphAlias) {
                return ToolResult::error('UNKNOWN_TYPE', "Unbekannter object_type '{$objectType}'. Bekannte Typen: project, package, task, issue, ticket, canvas, thread, contact, okr, note, employee, applicant, brand, process.");
            }

            // Resolve entity_id → dimension_value_id
            $def = OrganizationDimensionDefinition::findByKey('entity');
            if (!$def) {
                return ToolResult::error('CONFIG_ERROR', "Dimension 'entity' nicht konfiguriert.");
            }

            $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                ->where('metadata->source_entity_id', $entityId)
                ->first();

            if (!$dimValue) {
                return ToolResult::error('NOT_FOUND', "Entity {$entityId} hat keinen DimensionValue-Eintrag. Entity existiert möglicherweise nicht.");
            }

            // Verify the object exists
            $fqcn = Relation::getMorphedModel($morphAlias);
            if ($fqcn && class_exists($fqcn)) {
                $object = $fqcn::find($objectId);
                if (!$object) {
                    return ToolResult::error('NOT_FOUND', "{$objectType} mit ID {$objectId} nicht gefunden.");
                }
            }

            // Create the dimension link
            $service = new DimensionLinkService();
            $meta = [
                'team_id' => $context->team?->id ?? auth()->user()?->currentTeam?->id,
                'created_by_user_id' => $context->user?->id,
            ];

            $created = $service->link('entity', $morphAlias, $objectId, $dimValue->id, $meta);

            if (!$created) {
                return ToolResult::error('DUPLICATE', "Link existiert bereits: {$objectType} #{$objectId} → Entity #{$entityId}.");
            }

            return ToolResult::success([
                'entity_id' => $entityId,
                'entity_name' => $dimValue->name,
                'object_type' => $morphAlias,
                'object_id' => $objectId,
                'message' => "{$objectType} #{$objectId} erfolgreich mit Entity '{$dimValue->name}' (#{$entityId}) verknüpft.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verknüpfen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity', 'link', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

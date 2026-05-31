<?php

namespace Platform\Organization\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ResolveContextTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    /**
     * Friendly short names → morph alias.
     * Allows callers to use intuitive names without knowing the exact morph alias.
     */
    protected const ALIAS_MAP = [
        // Planner
        'project' => 'project',
        'planner_project' => 'project',
        'task' => 'planner_task',
        'planner_task' => 'planner_task',
        // Dev
        'package' => 'dev_package',
        'dev_package' => 'dev_package',
        'issue' => 'dev_issue',
        'dev_issue' => 'dev_issue',
        // OKR
        'okr' => 'okr',
        // Canvas
        'canvas' => 'canvas',
        'bmc_canvas' => 'bmc_canvas',
        'pc_canvas' => 'pc_canvas',
        // CRM
        'contact' => 'crm_contact',
        'crm_contact' => 'crm_contact',
        'company' => 'crm_company',
        'crm_company' => 'crm_company',
        // Helpdesk
        'ticket' => 'helpdesk_ticket',
        'helpdesk_ticket' => 'helpdesk_ticket',
        'helpdesk_board' => 'helpdesk_board',
        // HCM
        'employee' => 'hcm_employee',
        'hcm_employee' => 'hcm_employee',
        // Correspondence
        'thread' => 'correspondence_thread',
        'correspondence_thread' => 'correspondence_thread',
        // Whisper
        'recording' => 'whisper_recording',
        'whisper_recording' => 'whisper_recording',
        // Notes / Sheets / Slides
        'note' => 'notes_note',
        'notes_note' => 'notes_note',
        'spreadsheet' => 'sheets_spreadsheet',
        'sheets_spreadsheet' => 'sheets_spreadsheet',
        'presentation' => 'slides_presentation',
        'slides_presentation' => 'slides_presentation',
        // Recruiting
        'applicant' => 'rec_applicant',
        'rec_applicant' => 'rec_applicant',
        'position' => 'rec_position',
        'rec_position' => 'rec_position',
        // Process
        'process' => 'organization_process',
        'organization_process' => 'organization_process',
        // Change
        'change_project' => 'change_project',
        // Drip
        'bank_account_group' => 'drip_bank_account_group',
        'drip_bank_account_group' => 'drip_bank_account_group',
        'bank_transaction' => 'drip_bank_transaction',
        'drip_bank_transaction' => 'drip_bank_transaction',
        // Brands
        'brand' => 'brands_brand',
        'brands_brand' => 'brands_brand',
        // SEO
        'seo_url' => 'seo_url',
        'seo_url_list' => 'seo_url_list',
    ];

    public function getName(): string
    {
        return 'organization.context.resolve.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/context/resolve - Löst von jedem Objekt im System (Projekt, Task, Ticket, Canvas etc.) die zugehörige Entity-Struktur auf. Gibt verknüpfte Entities mit vollständigem Org-Pfad zurück (Entity → Parent → Root). Akzeptiert einfache Namen wie "project", "task", "ticket" — kein Vorwissen über interne Typen nötig.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'object_type' => [
                    'type' => 'string',
                    'description' => 'Typ des Objekts. Einfache Namen: project, task, ticket, canvas, thread, contact, okr, issue, package, note, employee, applicant, process, brand, spreadsheet, presentation, recording. Oder exakter Morph-Alias.',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Objekts.',
                ],
            ],
            'required' => ['object_type', 'object_id'],
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

            $objectType = $arguments['object_type'];
            $objectId = (int) $arguments['object_id'];

            // Resolve friendly name → morph alias
            $morphAlias = self::ALIAS_MAP[$objectType] ?? null;

            // If not in our map, check if it's a valid morph alias directly
            if (!$morphAlias && Relation::getMorphedModel($objectType)) {
                $morphAlias = $objectType;
            }

            // Last resort: check if it's a FQCN
            if (!$morphAlias) {
                $reverseMorphMap = array_flip(Relation::morphMap());
                if (isset($reverseMorphMap[$objectType])) {
                    $morphAlias = $reverseMorphMap[$objectType];
                }
            }

            if (!$morphAlias) {
                $knownTypes = array_keys(array_unique(self::ALIAS_MAP));
                sort($knownTypes);
                return ToolResult::error('UNKNOWN_TYPE', "Unbekannter object_type '{$objectType}'. Bekannte Typen: " . implode(', ', $knownTypes));
            }

            // Get the FQCN to query the linkable_type (DB may store alias or FQCN)
            $fqcn = Relation::getMorphedModel($morphAlias);
            $linkableTypes = [$morphAlias];
            if ($fqcn) {
                $linkableTypes[] = $fqcn;
            }

            // Find linked entities via dimension bridge
            $links = EntityDimensionBridge::linksForLinkables($linkableTypes, [$objectId], true);

            if ($links->isEmpty()) {
                return ToolResult::success([
                    'object_type' => $objectType,
                    'resolved_morph_alias' => $morphAlias,
                    'object_id' => $objectId,
                    'entities' => [],
                    'message' => 'Keine Entity-Verknüpfung gefunden für dieses Objekt.',
                ]);
            }

            // Build entity results with org path
            $entities = [];
            foreach ($links as $link) {
                $entity = $link->relationLoaded('entity') ? $link->getRelation('entity') : null;
                if (!$entity) {
                    continue;
                }

                // Build org path: walk up parent chain
                $path = [];
                $current = $entity;
                $visited = [];
                while ($current && !in_array($current->id, $visited)) {
                    $visited[] = $current->id;
                    $path[] = [
                        'id' => $current->id,
                        'name' => $current->name,
                        'type_name' => $current->type?->name,
                    ];
                    if ($current->parent_entity_id) {
                        $current = OrganizationEntity::where('team_id', $rootTeamId)
                            ->with('type')
                            ->find($current->parent_entity_id);
                    } else {
                        $current = null;
                    }
                }

                $entities[] = [
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                    'entity_type' => $entity->type?->name,
                    'org_path' => $path,
                    'org_path_display' => implode(' → ', array_column($path, 'name')),
                ];
            }

            return ToolResult::success([
                'object_type' => $objectType,
                'resolved_morph_alias' => $morphAlias,
                'object_id' => $objectId,
                'entities' => $entities,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auflösen des Kontexts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'context', 'resolve', 'traversal'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

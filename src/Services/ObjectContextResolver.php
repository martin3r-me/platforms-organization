<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Löst ein beliebiges Modul-Objekt (Projekt, Task, Ticket, …) auf die
 * verknüpften Entities auf — direkt über DimensionLinks oder, falls das Objekt
 * selbst nicht verortet ist, über sein Parent-Objekt (Task → Projekt).
 *
 * Gemeinsame Basis für organization.context.resolve.GET und organization.brief.GET.
 */
class ObjectContextResolver
{
    /**
     * Parent fallback: morph alias → [fk_field, parent_morph_alias].
     * When an object has no direct entity link, resolve via its parent.
     * fk_field darf ein Relationspfad sein (data_get), z. B. Issue → Board → Package.
     */
    public const PARENT_MAP = [
        'planner_task' => ['project_id', 'project'],
        'dev_issue' => ['board.dev_package_id', 'dev_package'],
        'helpdesk_ticket' => ['helpdesk_board_id', 'helpdesk_board'],
        'change_action' => ['change_project_id', 'change_project'],
        'change_phase' => ['change_project_id', 'change_project'],
    ];

    /**
     * Friendly short names → morph alias.
     * Allows callers to use intuitive names without knowing the exact morph alias.
     */
    public const ALIAS_MAP = [
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

    /**
     * Friendly name / Morph-Alias / FQCN → Morph-Alias, oder null.
     */
    public static function resolveAlias(string $objectType): ?string
    {
        if (isset(self::ALIAS_MAP[$objectType])) {
            return self::ALIAS_MAP[$objectType];
        }
        if (Relation::getMorphedModel($objectType)) {
            return $objectType;
        }
        $reverse = array_flip(Relation::morphMap());

        return $reverse[$objectType] ?? null;
    }

    /** @return string[] */
    public static function knownTypes(): array
    {
        $types = array_keys(array_unique(self::ALIAS_MAP));
        sort($types);

        return $types;
    }

    /**
     * Entity-Links des Objekts (mit eager-geladener Entity), inkl. Parent-Fallback.
     *
     * @return array{links: Collection, resolved_via: ?string}
     */
    public static function entityLinks(string $morphAlias, int $objectId): array
    {
        $links = EntityDimensionBridge::linksForLinkables(self::linkableTypes($morphAlias), [$objectId], true);
        $resolvedVia = null;

        if ($links->isEmpty() && isset(self::PARENT_MAP[$morphAlias])) {
            [$fkField, $parentAlias] = self::PARENT_MAP[$morphAlias];
            $fqcn = Relation::getMorphedModel($morphAlias);

            if ($fqcn && class_exists($fqcn)) {
                $parentId = data_get($fqcn::find($objectId), $fkField);
                if ($parentId) {
                    $links = EntityDimensionBridge::linksForLinkables(self::linkableTypes($parentAlias), [$parentId], true);
                    if ($links->isNotEmpty()) {
                        $resolvedVia = $parentAlias;
                    }
                }
            }
        }

        return ['links' => $links, 'resolved_via' => $resolvedVia];
    }

    /** DB kann Alias oder FQCN als linkable_type halten. */
    private static function linkableTypes(string $morphAlias): array
    {
        $types = [$morphAlias];
        if ($fqcn = Relation::getMorphedModel($morphAlias)) {
            $types[] = $fqcn;
        }

        return $types;
    }
}

<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlinkCategory;
use Platform\Organization\Models\OrganizationInterlink;

class DeleteInterlinkCategoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.interlink_categories.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/interlink-categories/{id} - Löscht eine Interlink-Kategorie. Verweigert wenn noch Interlinks diese Kategorie verwenden.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'interlink_category_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Interlink-Kategorie (ERFORDERLICH). Nutze organization.interlink_categories.GET.',
                ],
            ],
            'required' => ['interlink_category_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'interlink_category_id',
                OrganizationInterlinkCategory::class,
                'NOT_FOUND',
                'Interlink-Kategorie nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationInterlinkCategory $cat */
            $cat = $found['model'];

            $hasInterlinks = OrganizationInterlink::query()
                ->where('category_id', $cat->id)
                ->exists();
            if ($hasInterlinks) {
                return ToolResult::error('VALIDATION_ERROR', 'Interlink-Kategorie wird noch von Interlinks verwendet und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $cat->delete();

            return ToolResult::success([
                'id' => $cat->id,
                'message' => 'Interlink-Kategorie erfolgreich gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Interlink-Kategorie: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_categories', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

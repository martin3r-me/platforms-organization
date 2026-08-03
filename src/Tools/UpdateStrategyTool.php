<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationStrategy;

class UpdateStrategyTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.strategies.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/strategies/{id} - Aktualisiert das Strategie-Aggregat einer Carrier-Entity: '
            . 'status (draft|active|archived), version, published_at, owner_user_id. Adressierung per id ODER entity_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'            => ['type' => 'integer', 'description' => 'Strategy-ID (id ODER entity_id erforderlich).'],
                'entity_id'     => ['type' => 'integer', 'description' => 'Carrier-Entity (Alternative zu id).'],
                'status'        => ['type' => 'string', 'description' => 'draft | active | archived.'],
                'version'       => ['type' => 'integer', 'description' => 'Versions-Nummer.'],
                'published_at'  => ['type' => 'string', 'description' => 'ISO-Zeitpunkt der Veroeffentlichung (oder null).'],
                'owner_user_id' => ['type' => 'integer', 'description' => 'Verantwortliche:r User.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $entityId = (int) ($arguments['entity_id'] ?? 0);

            $strategy = $id > 0
                ? OrganizationStrategy::find($id)
                : ($entityId > 0 ? OrganizationStrategy::where('entity_id', $entityId)->first() : null);

            if (!$strategy) {
                return ToolResult::error('NOT_FOUND', 'Strategy nicht gefunden (id oder entity_id angeben).');
            }

            if (array_key_exists('status', $arguments)) {
                $allowed = [OrganizationStrategy::STATUS_DRAFT, OrganizationStrategy::STATUS_ACTIVE, OrganizationStrategy::STATUS_ARCHIVED];
                if (!in_array($arguments['status'], $allowed, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss draft, active oder archived sein.');
                }
                $strategy->status = $arguments['status'];
            }
            foreach (['version', 'published_at', 'owner_user_id'] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $strategy->{$col} = $arguments[$col];
                }
            }

            $strategy->save();

            return ToolResult::success([
                'id'           => $strategy->id,
                'entity_id'    => $strategy->entity_id,
                'status'       => $strategy->status,
                'version'      => $strategy->version,
                'published_at' => $strategy->published_at?->toIso8601String(),
                'owner_user_id' => $strategy->owner_user_id,
                'message'      => 'Aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'strategies', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}

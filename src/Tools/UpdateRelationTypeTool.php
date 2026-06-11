<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityRelationType;

class UpdateRelationTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.relation_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/relation-types/{id} - Aktualisiert einen Relation Type inkl. Beer-Channel-Properties (affects_aggregation, channel_class, traversal_direction, ...). Parameter: relation_type_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'relation_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Relation Types (ERFORDERLICH). Nutze organization.relation_types.GET.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Name.'],
                'code' => ['type' => 'string', 'description' => 'Optional: Code (muss eindeutig sein).'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung ("" zum Leeren).'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Icon-Bezeichnung ("" zum Leeren).'],
                'sort_order' => ['type' => 'integer', 'description' => 'Optional: Sortierreihenfolge.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: aktiv/inaktiv.'],
                'is_directional' => ['type' => 'boolean', 'description' => 'Optional: gerichtet ja/nein.'],
                'is_hierarchical' => ['type' => 'boolean', 'description' => 'Optional: hierarchisch ja/nein.'],
                'is_reciprocal' => ['type' => 'boolean', 'description' => 'Optional: reziprok ja/nein.'],

                // --- Beer-Channel-Properties ---
                'affects_aggregation' => ['type' => 'boolean', 'description' => 'Optional: Triggert Snapshot/Movement-Aggregation?'],
                'is_recursive' => ['type' => 'boolean', 'description' => 'Optional: Mehrere Hops transitiv folgen?'],
                'cascade_to_children' => ['type' => 'boolean', 'description' => 'Optional: Tree-Children der Quelle erben die Relation?'],
                'aggregation_weight' => ['type' => 'number', 'description' => 'Optional: Variety-Gewicht (Default 1.0).'],
                'traversal_direction' => [
                    'type' => 'string',
                    'enum' => ['forward', 'reverse', 'both'],
                    'description' => 'Optional: forward/reverse/both.',
                ],
                'inverse_code' => ['type' => 'string', 'description' => 'Optional: Code der Umkehr-Relation ("" zum Leeren).'],
                'allowed_from_types' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Erlaubte Quell-Entity-Types (leeres Array = null).'],
                'allowed_to_types' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Erlaubte Ziel-Entity-Types (leeres Array = null).'],
                'cardinality' => [
                    'type' => 'string',
                    'enum' => ['1:1', '1:N', 'N:M'],
                    'description' => 'Optional: Multiplizitaet.',
                ],
                'channel_class' => [
                    'type' => 'string',
                    'enum' => ['operational', 'informational', 'structural', 'algedonic', 'environmental'],
                    'description' => 'Optional: Beer-Channel-Klasse ("" zum Leeren).',
                ],
                'variety_flow' => [
                    'type' => 'string',
                    'enum' => ['from_to', 'to_from', 'bidirectional', 'none'],
                    'description' => 'Optional: Variety-Flussrichtung.',
                ],
                'capabilities' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Capability-Tags.'],
                'metadata' => ['type' => 'object', 'description' => 'Optional: Metadatenobjekt (null zum Leeren).'],
            ],
            'required' => ['relation_type_id'],
        ]);
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
                'relation_type_id',
                OrganizationEntityRelationType::class,
                'NOT_FOUND',
                'Relation Type nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityRelationType $rt */
            $rt = $found['model'];

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('code', $arguments)) {
                $code = trim((string)($arguments['code'] ?? ''));
                if ($code === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'code darf nicht leer sein.');
                }
                $exists = OrganizationEntityRelationType::query()
                    ->where('code', $code)
                    ->where('id', '!=', $rt->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Relation Type mit code '{$code}' existiert bereits.");
                }
                $update['code'] = $code;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('icon', $arguments)) {
                $i = (string)($arguments['icon'] ?? '');
                $update['icon'] = $i === '' ? null : $i;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int)$arguments['sort_order'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }
            foreach (['is_directional', 'is_hierarchical', 'is_reciprocal'] as $boolField) {
                if (array_key_exists($boolField, $arguments)) {
                    $update[$boolField] = (bool)$arguments[$boolField];
                }
            }

            // --- Beer-Channel-Properties ---
            foreach (['affects_aggregation', 'is_recursive', 'cascade_to_children'] as $boolField) {
                if (array_key_exists($boolField, $arguments)) {
                    $update[$boolField] = (bool)$arguments[$boolField];
                }
            }
            if (array_key_exists('aggregation_weight', $arguments)) {
                $update['aggregation_weight'] = (float)$arguments['aggregation_weight'];
            }
            if (array_key_exists('traversal_direction', $arguments)) {
                $update['traversal_direction'] = (string)$arguments['traversal_direction'];
            }
            if (array_key_exists('inverse_code', $arguments)) {
                $v = (string)($arguments['inverse_code'] ?? '');
                $update['inverse_code'] = $v === '' ? null : $v;
            }
            if (array_key_exists('allowed_from_types', $arguments)) {
                $a = $arguments['allowed_from_types'];
                $update['allowed_from_types'] = (is_array($a) && count($a) > 0) ? $a : null;
            }
            if (array_key_exists('allowed_to_types', $arguments)) {
                $a = $arguments['allowed_to_types'];
                $update['allowed_to_types'] = (is_array($a) && count($a) > 0) ? $a : null;
            }
            if (array_key_exists('cardinality', $arguments)) {
                $update['cardinality'] = (string)$arguments['cardinality'];
            }
            if (array_key_exists('channel_class', $arguments)) {
                $v = (string)($arguments['channel_class'] ?? '');
                $update['channel_class'] = $v === '' ? null : $v;
            }
            if (array_key_exists('variety_flow', $arguments)) {
                $update['variety_flow'] = (string)$arguments['variety_flow'];
            }
            if (array_key_exists('capabilities', $arguments)) {
                $a = $arguments['capabilities'];
                $update['capabilities'] = (is_array($a) && count($a) > 0) ? $a : null;
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $rt->update($update);
            }
            $rt->refresh();

            return ToolResult::success($this->present($rt) + [
                'message' => 'Relation Type erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Relation Types: ' . $e->getMessage());
        }
    }

    protected function present(OrganizationEntityRelationType $rt): array
    {
        return [
            'id' => $rt->id,
            'code' => $rt->code,
            'name' => $rt->name,
            'description' => $rt->description,
            'icon' => $rt->icon,
            'sort_order' => $rt->sort_order,
            'is_active' => (bool)$rt->is_active,
            'is_directional' => (bool)$rt->is_directional,
            'is_hierarchical' => (bool)$rt->is_hierarchical,
            'is_reciprocal' => (bool)$rt->is_reciprocal,
            'affects_aggregation' => (bool)$rt->affects_aggregation,
            'is_recursive' => (bool)$rt->is_recursive,
            'cascade_to_children' => (bool)$rt->cascade_to_children,
            'aggregation_weight' => (float)$rt->aggregation_weight,
            'traversal_direction' => $rt->traversal_direction,
            'inverse_code' => $rt->inverse_code,
            'allowed_from_types' => $rt->allowed_from_types,
            'allowed_to_types' => $rt->allowed_to_types,
            'cardinality' => $rt->cardinality,
            'channel_class' => $rt->channel_class,
            'variety_flow' => $rt->variety_flow,
            'capabilities' => $rt->capabilities,
            'metadata' => $rt->metadata,
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'relation_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

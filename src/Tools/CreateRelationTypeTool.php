<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityRelationType;

class CreateRelationTypeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.relation_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/relation-types - Erstellt einen Entity Relation Type (global). Inklusive Beer-Channel-Properties (affects_aggregation, channel_class, traversal_direction, ...). Nutze organization.relation_types.GET um bestehende zu pruefen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Relation Types (ERFORDERLICH).',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Eindeutiger Code (ERFORDERLICH, muss eindeutig sein).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Bezeichnung.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
                'is_directional' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation gerichtet (A->B != B->A)? Default: false.',
                    'default' => false,
                ],
                'is_hierarchical' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation hierarchisch (z.B. Parent->Child)? Default: false.',
                    'default' => false,
                ],
                'is_reciprocal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation reziprok (A<->B automatisch)? Default: false.',
                    'default' => false,
                ],

                // --- Beer-Channel-Properties --------------------------------

                'affects_aggregation' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Triggert dieser Type Snapshot/Movement-Aggregation? Default: false. Setze true fuer operative Channels (Resource Bargain, Steuerung, etc.).',
                    'default' => false,
                ],
                'is_recursive' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Folgt das Aggregations-Traversal mehrere Hops (transitiv)? Default: false.',
                    'default' => false,
                ],
                'cascade_to_children' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Erben Tree-Children der Quell-Entity die Relation? Default: false.',
                    'default' => false,
                ],
                'aggregation_weight' => [
                    'type' => 'number',
                    'description' => 'Optional: Variety-Gewicht (0.0 - 9.9999). Default: 1.0. Werte < 1.0 schwaechen den Aggregations-Beitrag ab.',
                    'default' => 1.0,
                ],
                'traversal_direction' => [
                    'type' => 'string',
                    'enum' => ['forward', 'reverse', 'both'],
                    'description' => 'Optional: Traversal-Richtung. forward = from->to, reverse = to->from, both = beide. Default: forward.',
                    'default' => 'forward',
                ],
                'inverse_code' => [
                    'type' => 'string',
                    'description' => 'Optional: Code der Umkehr-Relation (z.B. engagement_with <-> engaged_by).',
                ],
                'allowed_from_types' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Erlaubte Entity-Type-Codes als Quelle. null = alle erlaubt.',
                ],
                'allowed_to_types' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Erlaubte Entity-Type-Codes als Ziel. null = alle erlaubt.',
                ],
                'cardinality' => [
                    'type' => 'string',
                    'enum' => ['1:1', '1:N', 'N:M'],
                    'description' => 'Optional: Multiplizitaets-Constraint. Default: N:M.',
                    'default' => 'N:M',
                ],
                'channel_class' => [
                    'type' => 'string',
                    'enum' => ['operational', 'informational', 'structural', 'algedonic', 'environmental'],
                    'description' => 'Optional: Beer-Channel-Klasse. operational = Resource Bargain/Steuerung, informational = reine Info, structural = Tree-aequivalent, algedonic = Notruf, environmental = Umwelt-Probe.',
                ],
                'variety_flow' => [
                    'type' => 'string',
                    'enum' => ['from_to', 'to_from', 'bidirectional', 'none'],
                    'description' => 'Optional: Variety-Flussrichtung. Default: none.',
                    'default' => 'none',
                ],
                'capabilities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Frei taggbare Capability-Strings (z.B. ["supports_billing", "requires_approval"]).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name', 'code'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $code = trim((string)($arguments['code'] ?? ''));
            if ($code === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code ist erforderlich.');
            }

            $exists = OrganizationEntityRelationType::query()
                ->where('code', $code)
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Relation Type mit code '{$code}' existiert bereits.");
            }

            $payload = [
                'name' => $name,
                'code' => $code,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'icon' => (array_key_exists('icon', $arguments) && $arguments['icon'] !== '') ? (string)$arguments['icon'] : null,
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'is_directional' => (bool)($arguments['is_directional'] ?? false),
                'is_hierarchical' => (bool)($arguments['is_hierarchical'] ?? false),
                'is_reciprocal' => (bool)($arguments['is_reciprocal'] ?? false),

                // --- Beer-Channel-Properties ---
                'affects_aggregation' => (bool)($arguments['affects_aggregation'] ?? false),
                'is_recursive' => (bool)($arguments['is_recursive'] ?? false),
                'cascade_to_children' => (bool)($arguments['cascade_to_children'] ?? false),
                'aggregation_weight' => isset($arguments['aggregation_weight']) ? (float)$arguments['aggregation_weight'] : 1.0,
                'traversal_direction' => (string)($arguments['traversal_direction'] ?? 'forward'),
                'inverse_code' => (array_key_exists('inverse_code', $arguments) && $arguments['inverse_code'] !== '') ? (string)$arguments['inverse_code'] : null,
                'allowed_from_types' => isset($arguments['allowed_from_types']) && is_array($arguments['allowed_from_types']) ? $arguments['allowed_from_types'] : null,
                'allowed_to_types' => isset($arguments['allowed_to_types']) && is_array($arguments['allowed_to_types']) ? $arguments['allowed_to_types'] : null,
                'cardinality' => (string)($arguments['cardinality'] ?? 'N:M'),
                'channel_class' => (array_key_exists('channel_class', $arguments) && $arguments['channel_class'] !== '') ? (string)$arguments['channel_class'] : null,
                'variety_flow' => (string)($arguments['variety_flow'] ?? 'none'),
                'capabilities' => isset($arguments['capabilities']) && is_array($arguments['capabilities']) ? $arguments['capabilities'] : null,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ];

            $rt = OrganizationEntityRelationType::create($payload);

            return ToolResult::success($this->present($rt) + [
                'message' => 'Relation Type erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Relation Types: ' . $e->getMessage());
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
            'tags' => ['organization', 'relation_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

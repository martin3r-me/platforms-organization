<?php

namespace Platform\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Organization\Models\OrganizationEntityRelationType;

class OrganizationEntityRelationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $relationTypes = [
            // Hierarchische Beziehungen
            [
                'code' => 'reports_to',
                'name' => 'Berichtet an',
                'description' => 'Direkte Berichtslinie zu einer übergeordneten Einheit oder Person',
                'icon' => 'arrow-up',
                'sort_order' => 1,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => true,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'manages',
                'name' => 'Führt an',
                'description' => 'Führungsverantwortung für eine Einheit oder Person',
                'icon' => 'arrow-down',
                'sort_order' => 2,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => true,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'is_part_of',
                'name' => 'Ist Teil von',
                'description' => 'Zugehörigkeit zu einer übergeordneten Einheit',
                'icon' => 'link',
                'sort_order' => 3,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => true,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'contains',
                'name' => 'Enthält',
                'description' => 'Enthält untergeordnete Einheiten oder Personen',
                'icon' => 'folder',
                'sort_order' => 4,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => true,
                'is_reciprocal' => false,
            ],

            // Arbeitsbeziehungen
            [
                'code' => 'works_for',
                'name' => 'Arbeitet für',
                'description' => 'Beschäftigungsverhältnis mit einer Einheit',
                'icon' => 'briefcase',
                'sort_order' => 5,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'employs',
                'name' => 'Beschäftigt',
                'description' => 'Beschäftigt eine Person oder Einheit',
                'icon' => 'user-plus',
                'sort_order' => 6,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'collaborates_with',
                'name' => 'Arbeitet zusammen mit',
                'description' => 'Zusammenarbeit zwischen Einheiten oder Personen',
                'icon' => 'users',
                'sort_order' => 7,
                'is_active' => true,
                'is_directional' => false,
                'is_hierarchical' => false,
                'is_reciprocal' => true,
            ],

            // Funktionale Beziehungen
            [
                'code' => 'supports',
                'name' => 'Unterstützt',
                'description' => 'Unterstützungsfunktion für eine Einheit',
                'icon' => 'hand-raised',
                'sort_order' => 8,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'is_supported_by',
                'name' => 'Wird unterstützt von',
                'description' => 'Erhält Unterstützung von einer Einheit',
                'icon' => 'hand-raised',
                'sort_order' => 9,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'provides_service_to',
                'name' => 'Erbringt Dienstleistung für',
                'description' => 'Service-Erbringung für eine Einheit',
                'icon' => 'wrench-screwdriver',
                'sort_order' => 10,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'receives_service_from',
                'name' => 'Erhält Dienstleistung von',
                'description' => 'Erhält Service von einer Einheit',
                'icon' => 'wrench-screwdriver',
                'sort_order' => 11,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],

            // Externe Beziehungen
            [
                'code' => 'partners_with',
                'name' => 'Partnerschaft mit',
                'description' => 'Partnerschaft mit externer Einheit',
                'icon' => 'handshake',
                'sort_order' => 12,
                'is_active' => true,
                'is_directional' => false,
                'is_hierarchical' => false,
                'is_reciprocal' => true,
            ],
            [
                'code' => 'supplies_to',
                'name' => 'Liefert an',
                'description' => 'Lieferantenbeziehung zu einer Einheit',
                'icon' => 'truck',
                'sort_order' => 13,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'purchases_from',
                'name' => 'Kauft von',
                'description' => 'Einkaufsbeziehung von einer Einheit',
                'icon' => 'shopping-cart',
                'sort_order' => 14,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],

            // Rollenbeziehungen
            [
                'code' => 'has_role',
                'name' => 'Hat Rolle',
                'description' => 'Person hat eine bestimmte Rolle',
                'icon' => 'identification',
                'sort_order' => 15,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'role_holder',
                'name' => 'Rolleninhaber',
                'description' => 'Rolle wird von einer Person ausgeübt',
                'icon' => 'user-circle',
                'sort_order' => 16,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],

            // Kommunikationsbeziehungen
            [
                'code' => 'communicates_with',
                'name' => 'Kommuniziert mit',
                'description' => 'Kommunikationsbeziehung zwischen Einheiten',
                'icon' => 'chat-bubble-left-right',
                'sort_order' => 17,
                'is_active' => true,
                'is_directional' => false,
                'is_hierarchical' => false,
                'is_reciprocal' => true,
            ],
            [
                'code' => 'informs',
                'name' => 'Informiert',
                'description' => 'Informationsweitergabe an eine Einheit',
                'icon' => 'megaphone',
                'sort_order' => 18,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
            [
                'code' => 'is_informed_by',
                'name' => 'Wird informiert von',
                'description' => 'Erhält Informationen von einer Einheit',
                'icon' => 'megaphone',
                'sort_order' => 19,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],

            // Allgemeine Beziehungen
            [
                'code' => 'relates_to',
                'name' => 'Bezieht sich auf',
                'description' => 'Allgemeine Beziehung zu einer Einheit',
                'icon' => 'link',
                'sort_order' => 20,
                'is_active' => true,
                'is_directional' => false,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
            ],
        ];

        foreach ($relationTypes as $relationType) {
            OrganizationEntityRelationType::updateOrCreate(
                ['code' => $relationType['code']],
                $relationType
            );
        }
    }
}

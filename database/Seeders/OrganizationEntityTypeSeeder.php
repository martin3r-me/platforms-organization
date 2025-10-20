<?php

namespace Platform\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class OrganizationEntityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hole die Gruppen
        $groups = OrganizationEntityTypeGroup::all()->keyBy('name');

        $entityTypes = [
            // Organisationseinheiten
            [
                'code' => 'business_unit',
                'name' => 'Business Unit',
                'description' => 'Geschäftseinheit mit eigener Verantwortung',
                'icon' => 'building-office',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'corporate_service',
                'name' => 'Corporate Business Service',
                'description' => 'Zentrale Geschäftsdienste',
                'icon' => 'briefcase-globe',
                'sort_order' => 2,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'shared_service',
                'name' => 'Shared Service',
                'description' => 'Geteilte Dienste für mehrere Einheiten',
                'icon' => 'settings',
                'sort_order' => 3,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'profit_center',
                'name' => 'Profit Center',
                'description' => 'Gewinnverantwortliche Einheit',
                'icon' => 'currency-dollar',
                'sort_order' => 4,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'team',
                'name' => 'Team',
                'description' => 'Arbeitsgruppe oder Team',
                'icon' => 'users',
                'sort_order' => 6,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'board',
                'name' => 'Board / Gremium',
                'description' => 'Steuerungsgremium oder Board',
                'icon' => 'shield-check',
                'sort_order' => 7,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],
            [
                'code' => 'external_partner',
                'name' => 'Partnerunternehmen',
                'description' => 'Externes Partnerunternehmen',
                'icon' => 'handshake',
                'sort_order' => 8,
                'is_active' => true,
                'group_name' => 'Organisationseinheiten',
            ],

            // Personen
            [
                'code' => 'person',
                'name' => 'Person',
                'description' => 'Individuelle Person in der Organisation',
                'icon' => 'user',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Personen',
            ],

            // Rollen
            [
                'code' => 'role',
                'name' => 'Rolle',
                'description' => 'Funktionale Rolle oder Position',
                'icon' => 'briefcase',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Rollen',
            ],

            // Gruppen
            [
                'code' => 'communication_group',
                'name' => 'Kommunikationsgruppe',
                'description' => 'Gruppe für Kommunikation und Zusammenarbeit',
                'icon' => 'megaphone',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Gruppen',
            ],

            // Externe
            [
                'code' => 'external_customer',
                'name' => 'Externer Kunde',
                'description' => 'Externer Kunde oder Klient',
                'icon' => 'user-check',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Externe',
            ],
            [
                'code' => 'external_vendor',
                'name' => 'Externer Dienstleister',
                'description' => 'Externer Lieferant oder Dienstleister',
                'icon' => 'truck',
                'sort_order' => 2,
                'is_active' => true,
                'group_name' => 'Externe',
            ],
            [
                'code' => 'external_platform',
                'name' => 'Externe Plattform',
                'description' => 'Externe Plattform oder System',
                'icon' => 'cloud',
                'sort_order' => 3,
                'is_active' => true,
                'group_name' => 'Externe',
            ],
            [
                'code' => 'external',
                'name' => 'Externe Organisation',
                'description' => 'Allgemeine externe Organisation',
                'icon' => 'globe',
                'sort_order' => 4,
                'is_active' => true,
                'group_name' => 'Externe',
            ],

            // Technische Systeme
            [
                'code' => 'software',
                'name' => 'Software / System',
                'description' => 'Software oder technisches System',
                'icon' => 'server-cog',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Technische Systeme',
            ],

            // Erweiterte Kontexte
            [
                'code' => 'program',
                'name' => 'Programm',
                'description' => 'Programm oder Initiative',
                'icon' => 'boxes',
                'sort_order' => 1,
                'is_active' => true,
                'group_name' => 'Erweiterte Kontexte',
            ],
            [
                'code' => 'project',
                'name' => 'Projekt',
                'description' => 'Projekt oder Vorhaben',
                'icon' => 'folder-kanban',
                'sort_order' => 2,
                'is_active' => true,
                'group_name' => 'Erweiterte Kontexte',
            ],
            [
                'code' => 'region',
                'name' => 'Region',
                'description' => 'Geografische Region',
                'icon' => 'map',
                'sort_order' => 3,
                'is_active' => true,
                'group_name' => 'Erweiterte Kontexte',
            ],
            [
                'code' => 'product',
                'name' => 'Produkt',
                'description' => 'Produkt oder Dienstleistung',
                'icon' => 'package-check',
                'sort_order' => 4,
                'is_active' => true,
                'group_name' => 'Erweiterte Kontexte',
            ],
            [
                'code' => 'brand',
                'name' => 'Marke',
                'description' => 'Marke oder Brand',
                'icon' => 'badge-check',
                'sort_order' => 5,
                'is_active' => true,
                'group_name' => 'Erweiterte Kontexte',
            ],
        ];

        foreach ($entityTypes as $entityType) {
            $groupName = $entityType['group_name'];
            unset($entityType['group_name']); // Entferne group_name aus den Daten
            
            $group = $groups->get($groupName);
            if ($group) {
                $entityType['entity_type_group_id'] = $group->id;
                
                OrganizationEntityType::updateOrCreate(
                    ['code' => $entityType['code']],
                    $entityType
                );
            }
        }
    }
}

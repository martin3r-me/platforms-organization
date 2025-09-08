<?php

namespace Platform\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class OrganizationEntityTypeGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Organisationseinheiten',
                'description' => 'Teams, Abteilungen, Services und operative Einheiten der Organisation',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Personen',
                'description' => 'Individuelle Personen in der Organisation',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Rollen',
                'description' => 'Funktionale Rollen und Positionen',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Gruppen',
                'description' => 'Kommunikations- und Arbeitsgruppen',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Externe',
                'description' => 'Externe Partner, Kunden und Lieferanten',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Technische Systeme',
                'description' => 'Software, Tools und technische Plattformen',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Erweiterte Kontexte',
                'description' => 'Projekte, Programme, Regionen und strategische Kontexte',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Sonstige',
                'description' => 'Weitere Entitätstypen',
                'sort_order' => 99,
                'is_active' => true,
            ],
        ];

        foreach ($groups as $group) {
            OrganizationEntityTypeGroup::updateOrCreate(
                ['name' => $group['name']],
                $group
            );
        }
    }
}

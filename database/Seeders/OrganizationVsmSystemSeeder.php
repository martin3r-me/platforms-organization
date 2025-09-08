<?php

namespace Platform\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Platform\Organization\Models\OrganizationVsmSystem;

class OrganizationVsmSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vsmSystems = [
            [
                'code' => 'S1',
                'name' => 'System 1 – Operation',
                'description' => 'Operative Einheit, Wertschöpfung',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'S2',
                'name' => 'System 2 – Koordination',
                'description' => 'Koordination zwischen operativen Einheiten',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'S3',
                'name' => 'System 3 – Kontrolle',
                'description' => 'Zentrale Steuerung, Ressourcenmanagement',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'S4',
                'name' => 'System 4 – Intelligenz',
                'description' => 'Strategie, Innovation, Umweltbeobachtung',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'code' => 'S5',
                'name' => 'System 5 – Identität',
                'description' => 'Werte, Policy, Vision',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($vsmSystems as $system) {
            OrganizationVsmSystem::updateOrCreate(
                ['code' => $system['code']],
                $system
            );
        }
    }
}

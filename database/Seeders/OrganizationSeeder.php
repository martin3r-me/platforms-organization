<?php

namespace Platform\Organization\Database\Seeders;

use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // Lookup-Tables
            OrganizationVsmSystemSeeder::class,
            OrganizationEntityTypeGroupSeeder::class,
            OrganizationEntityTypeSeeder::class,
            OrganizationEntityRelationTypeSeeder::class,
            
            // Weitere Seeder werden hier hinzugefügt
            // OrganizationStrategicRelevanceSeeder::class,
            // etc.
        ]);
    }
}

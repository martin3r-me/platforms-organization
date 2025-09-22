<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Database\Seeders\OrganizationSeeder;

class SeedOrganizationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organization:seed-data {--force : Force the operation to run even in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the Organization module lookup and base data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding Organization data...');

        try {
            $seeder = new OrganizationSeeder();
            $seeder->run();

            $this->info('✅ Organization data seeded successfully!');
        } catch (\Throwable $e) {
            $this->error('❌ Failed to seed Organization data: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}



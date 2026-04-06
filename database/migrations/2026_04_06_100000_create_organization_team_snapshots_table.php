<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_team_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('snapshot_period', 10)->default('morning');
            $table->json('structure');
            $table->timestamp('created_at')->nullable();

            $table->unique(['team_id', 'snapshot_date', 'snapshot_period'], 'uq_team_date_period');
            $table->index('snapshot_date', 'idx_team_snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_team_snapshots');
    }
};

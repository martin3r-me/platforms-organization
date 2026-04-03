<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_entity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('snapshot_period', 10)->default('morning');
            $table->json('metrics');
            $table->timestamp('created_at')->nullable();

            $table->unique(['entity_id', 'snapshot_date', 'snapshot_period'], 'uq_entity_date_period');
            $table->index('snapshot_date', 'idx_snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_snapshots');
    }
};

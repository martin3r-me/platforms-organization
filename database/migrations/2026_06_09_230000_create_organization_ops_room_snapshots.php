<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taegliche Counter-Snapshots der Ops-Room-Brueke pro Perspektive.
 *
 * Zweck: historische Bilanz fuer Reflexion (Eigenwoche, Quartalsreview).
 * "Hat sich was geaendert? Mehr Algedonics? Weniger Vakanzen?"
 *
 * Pro Tag + Perspektive genau ein Eintrag (unique). Wird vom Daily-Cron
 * gefuellt; existierender Eintrag des Tages wird ueberschrieben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_ops_room_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('perspective_entity_id')
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->date('snapshot_date');

            // Top-Line totals
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('escalated_count')->default(0);
            $table->unsignedInteger('algedonic_count')->default(0);
            $table->unsignedInteger('vacant_cells_count')->default(0);

            // Per-Level breakdown: { s5: {open,esc,alg}, s4: {...}, ... }
            $table->json('per_level')->nullable();

            $table->timestamps();

            $table->unique(['perspective_entity_id', 'snapshot_date'], 'ops_snap_persp_date_unique');
            $table->index(['team_id', 'snapshot_date'], 'ops_snap_team_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_ops_room_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 4: entity_vsm_assignments.
 *
 * Pflegt pro Perspektive (= Carrier-Entity) die Besetzung der sechs
 * VSM-Zellen S1, S2, S3, S3-Star, S4, S5 mit Actor-Entities.
 *
 * Beispiel BHG.DIGITAL: S5 = Martin Erren + Burkhard Schmitz, S3 = Lenkungsausschuss,
 * S1 = Christian Wolf, Dominique Beutin, Max Walter, Philip Broich.
 *
 * Mehrfachbesetzung pro Zelle ist erlaubt. Carrier/Actor-Constraints werden
 * App-seitig im Modell-Saving-Hook erzwungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_entity_vsm_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('perspective_entity_id')
                ->comment('Aus wessen Sicht (Carrier-Entity)')
                ->constrained('organization_entities')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->string('vsm_system', 16)
                ->comment('s1, s2, s3, s3_star, s4, s5');

            $table->foreignId('assigned_entity_id')
                ->comment('Wer fuellt die Zelle aus (Actor-Entity)')
                ->constrained('organization_entities')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->text('scope')->nullable()
                ->comment('Optionale Einschraenkung, z.B. "Cashflow" oder "Backend"');

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Gleiche Zuordnung darf es nur einmal geben (innerhalb gleicher Perspektive + Zelle)
            $table->unique(
                ['perspective_entity_id', 'vsm_system', 'assigned_entity_id'],
                'org_vsm_assignment_unique'
            );

            // Lookups
            $table->index(['perspective_entity_id', 'vsm_system'], 'org_vsm_assignment_perspective_system_idx');
            $table->index('assigned_entity_id', 'org_vsm_assignment_assigned_idx');
            $table->index(['team_id', 'vsm_system'], 'org_vsm_assignment_team_system_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_vsm_assignments');
    }
};

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
 *
 * **FK-Namen explizit kurz vergeben**, weil Laravels Default-Naming
 * (`<table>_<column>_foreign`) bei dieser langen Tabelle die MySQL-Grenze
 * von 64 Zeichen sprengt. Explizit benannt: ovsm_team_fk, ovsm_perspective_fk,
 * ovsm_assigned_fk, ovsm_creator_fk.
 *
 * **Idempotent:** dropIfExists am Anfang. Sicher, weil eine vorherige
 * fehlgeschlagene Tabelle keine Daten enthalten kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: Teil-erzeugte Tabelle aus vorigem Fehlversuch abraeumen.
        Schema::dropIfExists('organization_entity_vsm_assignments');

        Schema::create('organization_entity_vsm_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('perspective_entity_id')
                ->comment('Aus wessen Sicht (Carrier-Entity)');
            $table->string('vsm_system', 16)
                ->comment('s1, s2, s3, s3_star, s4, s5');
            $table->unsignedBigInteger('assigned_entity_id')
                ->comment('Wer fuellt die Zelle aus (Actor-Entity)');

            $table->text('scope')->nullable()
                ->comment('Optionale Einschraenkung, z.B. "Cashflow" oder "Backend"');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FK-Namen explizit kurz, sonst >64 Zeichen (MySQL-Limit).
            $table->foreign('team_id', 'ovsm_team_fk')
                ->references('id')->on('teams')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('perspective_entity_id', 'ovsm_perspective_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('assigned_entity_id', 'ovsm_assigned_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('created_by_user_id', 'ovsm_creator_fk')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('set null');

            // Gleiche Zuordnung darf es nur einmal geben (innerhalb gleicher Perspektive + Zelle)
            $table->unique(
                ['perspective_entity_id', 'vsm_system', 'assigned_entity_id'],
                'ovsm_unique'
            );

            // Lookups
            $table->index(['perspective_entity_id', 'vsm_system'], 'ovsm_perspective_system_idx');
            $table->index('assigned_entity_id', 'ovsm_assigned_idx');
            $table->index(['team_id', 'vsm_system'], 'ovsm_team_system_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_vsm_assignments');
    }
};

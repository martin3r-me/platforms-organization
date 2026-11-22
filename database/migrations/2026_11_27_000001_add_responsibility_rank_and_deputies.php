<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zuständigkeit als Plattform-Fähigkeit: Rang an Beziehungsarten und Rollen (wer an einem Knoten zuerst
 * verantwortlich ist) und Vertretungen (A vertritt B, generell oder je Knoten, optional nur bei Abwesenheit).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organization_entity_relation_types', 'organization_roles'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'responsibility_rank')) {
                Schema::table($table, function (Blueprint $t) {
                    // kleiner = zuerst; null = ohne Rang (zählt nach allen mit Rang)
                    $t->unsignedSmallInteger('responsibility_rank')->nullable();
                });
            }
        }

        // Startwerte für die heutigen Führungsbeziehungen
        if (Schema::hasColumn('organization_entity_relation_types', 'responsibility_rank')) {
            foreach (['owns_venture' => 10, 'co_owns_venture' => 20, 'manages' => 30] as $code => $rank) {
                DB::table('organization_entity_relation_types')->where('code', $code)->whereNull('responsibility_rank')
                    ->update(['responsibility_rank' => $rank]);
            }
        }

        if (! Schema::hasTable('organization_deputies')) {
            Schema::create('organization_deputies', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $t->unsignedBigInteger('principal_entity_id')->index();   // wer vertreten wird
                $t->unsignedBigInteger('deputy_entity_id')->index();      // wer vertritt
                $t->unsignedBigInteger('scope_entity_id')->nullable();    // nur für diesen Knoten (und darunter); null = überall
                $t->boolean('only_when_absent')->default(true);
                $t->date('valid_from')->nullable();
                $t->date('valid_to')->nullable();
                $t->text('note')->nullable();
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_deputies');
        foreach (['organization_entity_relation_types', 'organization_roles'] as $table) {
            if (Schema::hasColumn($table, 'responsibility_rank')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('responsibility_rank'));
            }
        }
    }
};

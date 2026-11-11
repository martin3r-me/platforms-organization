<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spiegel der Regeln, aus denen die Arbeitsweise eines Engrams besteht (Art, Anlass, Herkunft, Bewährung,
 * Status, Ablösung). Quelle bleibt das Engram; geändert wird über Kuratier-Aufträge (Ziel rules/<id> bzw. rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_agent_rules')) {
            Schema::create('organization_agent_rules', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('organization_entity_id')->index();
                $t->unsignedBigInteger('rule_id');                 // Kennung im Engram (rN)
                $t->integer('ord')->default(0);
                $t->string('kind', 16);                            // step | rule | avoid
                $t->string('trigger', 500)->default('');           // leer = Kern
                $t->text('text');
                $t->text('why')->nullable();
                $t->string('source', 16)->default('learned');      // learned | import | curation
                $t->string('status', 16)->index();                 // proposed | probation | active | superseded | retired
                $t->unsignedBigInteger('supersedes')->default(0);
                $t->unsignedInteger('confirmed')->default(0);
                $t->unsignedInteger('refuted')->default(0);
                $t->char('sha', 64);
                $t->string('rule_created_at', 40)->nullable();
                $t->string('rule_updated_at', 40)->nullable();
                $t->string('last_used_at', 40)->nullable();
                $t->timestamps();
                $t->unique(['organization_entity_id', 'rule_id'], 'org_agent_rules_entity_rule');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_agent_rules');
    }
};

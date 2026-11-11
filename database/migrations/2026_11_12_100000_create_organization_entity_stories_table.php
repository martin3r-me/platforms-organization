<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knoten-Erzählung („Lage"): je Knoten, Zeitraum und Stichtag die Fakten (deterministisch aus
 * Snapshots + Quelle) und der Satz daraus — von der KI formuliert oder als fester Satz.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_entity_stories')) {
            return;
        }

        Schema::create('organization_entity_stories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('team_id')->index();
            $t->unsignedBigInteger('entity_id');
            $t->string('period', 10);                 // week | month | quarter
            $t->date('as_of');
            $t->json('facts');
            $t->text('story')->nullable();
            $t->string('source', 16)->default('template'); // llm | template
            $t->string('model')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();

            $t->unique(['entity_id', 'period', 'as_of'], 'org_entity_stories_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_stories');
    }
};

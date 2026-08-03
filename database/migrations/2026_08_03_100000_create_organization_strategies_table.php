<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

/**
 * Fuehrt das Strategy-Aggregat ein: eine Strategie 1:1 pro Carrier-Entity als
 * Lifecycle-/Meta-Container (Status, Version, Owner). Die bestehenden Artefakte
 * (Fokusraeume, Forecasts, StrategicDocuments) bleiben ueber entity_id verankert
 * — kein strategy_id-FK, da 1:1. Der Backfill legt fuer jeden Carrier mit
 * mindestens einem Artefakt eine Strategy an (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_strategies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('entity_id')->unique()->constrained('organization_entities')->cascadeOnDelete();

            $table->string('status')->default('draft'); // draft | active | archived
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_strategies');
    }

    /** Eine Strategy je Carrier-Entity mit mind. einem Strategie-Artefakt. */
    private function backfill(): void
    {
        $carriers = DB::table('organization_entities as e')
            ->join('organization_entity_types as t', 'e.entity_type_id', '=', 't.id')
            ->where('t.vsm_class', 'carrier')
            ->whereNull('e.deleted_at')
            ->select('e.id', 'e.team_id')
            ->get();

        $now = now();

        foreach ($carriers as $c) {
            if (DB::table('organization_strategies')->where('entity_id', $c->id)->exists()) {
                continue; // idempotent
            }

            $hasFocusArea = DB::table('organization_focus_areas')->where('entity_id', $c->id)->whereNull('deleted_at')->exists();
            $hasForecast  = DB::table('organization_forecasts')->where('entity_id', $c->id)->whereNull('deleted_at')->exists();
            $hasDoc       = DB::table('organization_strategic_documents')->where('entity_id', $c->id)->whereNull('deleted_at')->exists();

            if (! $hasFocusArea && ! $hasForecast && ! $hasDoc) {
                continue; // kein Artefakt → keine Strategy
            }

            DB::table('organization_strategies')->insert([
                'uuid'       => (string) UuidV7::generate(),
                'entity_id'  => $c->id,
                'status'     => ($hasForecast && $hasFocusArea) ? 'active' : 'draft',
                'version'    => 1,
                'team_id'    => $c->team_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};

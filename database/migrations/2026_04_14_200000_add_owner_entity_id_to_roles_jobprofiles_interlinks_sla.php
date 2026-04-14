<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_entity_id')->nullable()->after('status');
            $table->foreign('owner_entity_id')->references('id')->on('organization_entities')->nullOnDelete();
        });

        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_entity_id')->nullable()->after('status');
            $table->foreign('owner_entity_id')->references('id')->on('organization_entities')->nullOnDelete();
        });

        Schema::table('organization_interlinks', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_entity_id')->nullable()->after('metadata');
            $table->foreign('owner_entity_id')->references('id')->on('organization_entities')->nullOnDelete();
        });

        Schema::table('organization_sla_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_entity_id')->nullable()->after('is_active');
            $table->foreign('owner_entity_id')->references('id')->on('organization_entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->dropForeign(['owner_entity_id']);
            $table->dropColumn('owner_entity_id');
        });

        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->dropForeign(['owner_entity_id']);
            $table->dropColumn('owner_entity_id');
        });

        Schema::table('organization_interlinks', function (Blueprint $table) {
            $table->dropForeign(['owner_entity_id']);
            $table->dropColumn('owner_entity_id');
        });

        Schema::table('organization_sla_contracts', function (Blueprint $table) {
            $table->dropForeign(['owner_entity_id']);
            $table->dropColumn('owner_entity_id');
        });
    }
};

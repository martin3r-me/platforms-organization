<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_eri_sla_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_relationship_interlink_id');
            $table->unsignedBigInteger('sla_contract_id');

            $table->foreign('entity_relationship_interlink_id', 'org_eri_sla_eri_fk')
                ->references('id')->on('organization_entity_relationship_interlinks')->cascadeOnDelete();
            $table->foreign('sla_contract_id', 'org_eri_sla_contract_fk')
                ->references('id')->on('organization_sla_contracts')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['entity_relationship_interlink_id', 'sla_contract_id'], 'org_eri_sla_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_eri_sla_contracts');
    }
};

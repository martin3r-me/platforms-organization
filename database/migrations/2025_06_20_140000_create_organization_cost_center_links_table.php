<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_cost_center_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Verknüpfte Kostenstelle (Organization Entity vom Typ Kostenstelle)
            $table->foreignId('entity_id')->constrained('organization_entities')->cascadeOnDelete();

            // Polymorphe Verknüpfung (z. B. HcmEmployeeContract, HcmEmployee)
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');

            // Gültigkeitszeitraum und Gewichtung
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);

            // Multi-Tenant/Audit
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexe
            $table->index(['entity_id']);
            $table->index(['linkable_type', 'linkable_id']);
            $table->index(['team_id']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_cost_center_links');
    }
};



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
        Schema::create('organization_vsm_systems', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // S1, S2, S3, S4, S5
            $table->string('name'); // System 1 – Operation, etc.
            $table->text('description')->nullable(); // Operative Einheit, Wertschöpfung, etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_vsm_systems');
    }
};

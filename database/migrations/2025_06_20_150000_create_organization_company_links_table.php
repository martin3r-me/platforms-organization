<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_company_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_entity_id', 'team_id']);
            $table->index(['linkable_type', 'linkable_id']);
            $table->unique(['organization_entity_id','linkable_type','linkable_id'], 'org_company_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_company_links');
    }
};




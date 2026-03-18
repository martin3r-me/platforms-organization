<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_person_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('person_id')
                ->constrained('organization_persons')
                ->cascadeOnDelete();

            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'team_id'], 'opl_person_team_idx');
            $table->index(['linkable_type', 'linkable_id'], 'opl_linkable_idx');
            $table->index(['linkable_type', 'linkable_id', 'person_id'], 'opl_linkable_person_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_person_links');
    }
};

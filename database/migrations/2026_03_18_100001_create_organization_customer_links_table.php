<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_customer_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('customer_id')
                ->constrained('organization_customers')
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

            $table->index(['customer_id', 'team_id'], 'ocl_customer_team_idx');
            $table->index(['linkable_type', 'linkable_id'], 'ocl_linkable_idx');
            $table->index(['linkable_type', 'linkable_id', 'customer_id'], 'ocl_linkable_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_customer_links');
    }
};

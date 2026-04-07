<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('role_id')
                  ->constrained('organization_roles')
                  ->cascadeOnDelete();

            $table->foreignId('person_entity_id')
                  ->constrained('organization_entities')
                  ->cascadeOnDelete();

            $table->foreignId('context_entity_id')
                  ->constrained('organization_entities')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('percentage')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['person_entity_id']);
            $table->index(['context_entity_id']);
            $table->index(['role_id']);
            $table->index(['team_id']);
        });

        // Hard-Constraint: Person darf nicht Kontext sich selbst sein.
        // CHECK-Constraints werden nicht von allen MySQL/MariaDB-Versionen
        // gleichermassen unterstützt – daher mit Try/Catch absichern.
        try {
            DB::statement(
                'ALTER TABLE organization_role_assignments '
                .'ADD CONSTRAINT chk_role_assignment_person_neq_context '
                .'CHECK (person_entity_id <> context_entity_id)'
            );
        } catch (\Throwable $e) {
            // Falls die DB CHECK nicht unterstützt, greift die Model-Validierung.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_role_assignments');
    }
};

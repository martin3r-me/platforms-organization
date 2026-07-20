<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fremd-IDs einer Entity: die Identität dieser Organisationseinheit in
     * anderen Systemen (Kostenstelle, DATEV, Buchungskonto, Kreditor, …).
     *
     * Jede Entity IST faktisch ihre eigene Kostenstelle — die Kostenstelle ist
     * hier nur der erste `system`-Wert derselben Familie. Neue Fremd-Typen
     * brauchen nie eine Migration, nur einen neuen `system`-String.
     */
    public function up(): void
    {
        Schema::create('organization_entity_external_ids', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('entity_id')
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->string('system');            // 'kostenstelle' | 'datev' | 'buchungskonto' | 'kreditor' | …
            $table->string('value');             // 'KST-4200' | '10001' | '70000' …
            $table->string('label')->nullable(); // optional menschenlesbar
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Eine Entity hat je System höchstens eine ID.
            $table->unique(['team_id', 'system', 'entity_id']);
            // Rückwärts-Auflösung: "welche Entity ist DATEV 10001?"
            $table->index(['team_id', 'system', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_external_ids');
    }
};

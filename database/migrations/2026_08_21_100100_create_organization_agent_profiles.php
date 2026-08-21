<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent-Profil (1:1 zur agent-Entity): die Runtime-Config, die der Client-Daemon zieht, plus
 * der Status, den er zurückmeldet. KEINE Secrets hier — der Claude-Login (.credentials.json)
 * und das GitHub-Token liegen auf dem Client; hier nur Referenz (github_username) + Status.
 * Der Plattform-API-Token hängt am Bot-User (linked_user_id), nicht hier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_entity_id')->unique()->constrained()->cascadeOnDelete();

            // Config (Plattform → Daemon):
            // domain: operativ (development|backoffice|helpdesk|assistant, S1) ODER analysis (S2–S4).
            $table->string('domain')->nullable();
            $table->json('stages')->nullable();                       // z. B. ['triage','execute'] / ['signal']
            $table->unsignedSmallInteger('five_hour_reserve_pct')->default(90);
            $table->unsignedSmallInteger('seven_day_burn_margin_pct')->default(10);
            $table->boolean('active')->default(true);                 // an/aus (Daemon liest es)

            // Konto-Referenzen (kein Secret):
            $table->string('github_username')->nullable();

            // Status (Daemon → Plattform, read-only in der UI):
            $table->string('status')->nullable();                     // running|idle|cooldown|auth_expired|offline
            $table->string('claude_subscription')->nullable();
            $table->decimal('five_hour_pct', 5, 2)->nullable();
            $table->decimal('seven_day_pct', 5, 2)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_agent_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freigabe eines Knotens nach außen (Kunden-Tor): ein Knoten → eine URL, mit allem,
 * was daran hängt. Was sichtbar ist (Typen, Tiefe im Baum), regelt die Freigabe selbst.
 * Zugang per offenem Link oder nur für eingeladene E-Mail-Adressen (persönlicher Link je Person).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_entity_shares')) {
            Schema::create('organization_entity_shares', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->unsignedBigInteger('team_id')->index();
                $t->unsignedBigInteger('entity_id')->index();
                $t->string('token', 64)->unique();
                $t->string('title')->nullable();          // Anzeige-Titel für den Kunden (sonst Knotenname)
                $t->text('message')->nullable();          // Begrüßung auf der Seite
                $t->string('access', 16)->default('link'); // link | invite
                $t->unsignedTinyInteger('depth')->nullable(); // 0 = nur der Knoten, n Ebenen, null = alles darunter
                $t->json('types')->nullable();            // erlaubte Morph-Aliase, null = alle mit Kundenansicht
                $t->json('settings')->nullable();         // Detailgrad u. ä.
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->timestamp('last_viewed_at')->nullable();
                $t->unsignedInteger('view_count')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('organization_entity_share_recipients')) {
            Schema::create('organization_entity_share_recipients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('share_id')->index();
                $t->string('email');
                $t->string('token', 64)->unique();        // persönlicher Link
                $t->timestamp('invited_at')->nullable();
                $t->timestamp('last_viewed_at')->nullable();
                $t->unsignedInteger('view_count')->default(0);
                $t->timestamps();
                $t->unique(['share_id', 'email'], 'org_share_recipients_share_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_share_recipients');
        Schema::dropIfExists('organization_entity_shares');
    }
};

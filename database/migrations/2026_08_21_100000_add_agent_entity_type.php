<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entity-Type `agent`: ein KI-Worker als echtes Org-Mitglied — quasi eine Person (eigener
 * Bot-User via linked_user_id), aber mit einem Agent-Profil (Domäne, Stufen, Governor, Konto-
 * Status). Läuft als CLIENT (VM/Daemon), tut sichtbare, attribuierte, gestempelte Arbeit.
 *
 * Vereinheitlicht: was der frühere `system_agent` (verdeckte Backend-Inference für VSM-Signale)
 * tat, wird künftig eine DOMÄNE dieses Agenten (analysis/signal, S2–S4) — dann ebenfalls auf
 * einem Client, nicht mehr im Backend. Prinzip: die Plattform ruft nie selbst ein LLM; jede
 * KI-Kognition macht ein sichtbarer Agent. `vsm_class = actor`, kann S1–S4 ausfüllen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('organization_entity_types')->where('code', 'agent')->exists()) {
            return;
        }

        $groupId = DB::table('organization_entity_type_groups')
            ->where('name', 'Organisationseinheiten')
            ->value('id');

        DB::table('organization_entity_types')->insert([
            'code' => 'agent',
            'name' => 'Agent',
            'description' => 'KI-Worker als Org-Mitglied (quasi Person, eigener Bot-User). Läuft als Client (VM/Daemon) und tut sichtbare, attribuierte, gestempelte Arbeit. Domäne bestimmt die Aufgabe: operativ (S1: development/backoffice/helpdesk/assistant) oder analysis/signal (S2–S4, ersetzt den verdeckten system_agent). Die Plattform kognisiert nicht selbst.',
            'icon' => 'sparkles',
            'sort_order' => 9,
            'is_active' => true,
            'entity_type_group_id' => $groupId,
            'vsm_class' => 'actor',
            'can_be_perspective' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('organization_entity_types')->where('code', 'agent')->delete();
    }
};

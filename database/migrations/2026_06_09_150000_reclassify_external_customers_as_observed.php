<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * VSM-Phase 1, Schritt 1 Nachzieher: Strenge Beer-Klassifikation.
 *
 * Externe Kunden sind im strengen VSM-Sinn **Umwelt**, nicht Carrier.
 * Sie erzeugen Bewegungsdaten (Auftraege, Tickets, Zahlungen, NPS), die
 * von S4 beobachtet und in S1/S3 verarbeitet werden — aber sie selbst
 * fuellen keine Funktion in unserem System aus.
 *
 * Daher:
 *  - external_customer: carrier -> observed
 *  - customer_department: actor -> observed
 *
 * Fuer Faelle, in denen ein "Kunde" tatsaechlich strategisch im Netzwerk
 * verbunden ist (Tochter, Beteiligung, langjaehriger Strategie-Partner),
 * wird ein neuer Type `network_customer` als Carrier eingefuehrt. Das
 * macht Carrier-Status zur **bewussten Entscheidung** statt zum Default
 * fuer jeden Kontakt.
 *
 * Migration ist idempotent — Updates sind whereIn-basiert, Insert mit
 * Existenz-Check.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_entity_types')
            ->whereIn('code', ['external_customer', 'customer_department'])
            ->update([
                'vsm_class' => 'observed',
                'can_be_perspective' => false,
                'updated_at' => now(),
            ]);

        $exists = DB::table('organization_entity_types')
            ->where('code', 'network_customer')
            ->exists();

        if (!$exists) {
            $groupId = DB::table('organization_entity_type_groups')
                ->where('name', 'Organisationseinheiten')
                ->value('id');

            DB::table('organization_entity_types')->insert([
                'code' => 'network_customer',
                'name' => 'Netzwerk-Kunde',
                'description' => 'Unternehmen im strategischen Netzwerk (Tochter, Beteiligung, langjaehriger Strategie-Partner). Eigene Wertschoepfungseinheit mit gemeinsamer Identitaet und Steuerung — im VSM als Carrier modelliert, weil aktive bidirektionale Steuerung sinnvoll ist. Abgrenzung zu external_customer: dort ist die Beziehung rein transaktional/extern (Umwelt).',
                'icon' => 'building-office-2',
                'sort_order' => 7,
                'is_active' => true,
                'entity_type_group_id' => $groupId,
                'vsm_class' => 'carrier',
                'can_be_perspective' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('organization_entity_types')
            ->where('code', 'network_customer')
            ->delete();

        DB::table('organization_entity_types')
            ->where('code', 'external_customer')
            ->update([
                'vsm_class' => 'carrier',
                'can_be_perspective' => true,
                'updated_at' => now(),
            ]);

        DB::table('organization_entity_types')
            ->where('code', 'customer_department')
            ->update([
                'vsm_class' => 'actor',
                'can_be_perspective' => false,
                'updated_at' => now(),
            ]);
    }
};

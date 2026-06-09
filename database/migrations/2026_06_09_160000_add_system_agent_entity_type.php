<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * VSM-Phase 1, Schritt 2a: Entity-Type `system_agent`.
 *
 * Neuer EntityType fuer automatisierte Funktionstraeger (z.B. Inference-Agents,
 * Cron-Watcher, RSS-Monitore). Wird im Baum gepflegt wie Personen oder
 * Capability-Areas — `vsm_class = actor`, `can_be_perspective = false`.
 *
 * Parent-Entity = der Knoten, dessen Kontext der Agent primaer bedient
 * (z.B. Inbox Triage Agent unter BHG.DIGITAL).
 *
 * Verknuepfung zu OrganizationSignalInferencePrompt erfolgt in Migration 2b
 * via FK `agent_entity_id` am Prompt (N:1).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('organization_entity_types')
            ->where('code', 'system_agent')
            ->exists();

        if ($exists) {
            return;
        }

        $groupId = DB::table('organization_entity_type_groups')
            ->where('name', 'Organisationseinheiten')
            ->value('id');

        DB::table('organization_entity_types')->insert([
            'code' => 'system_agent',
            'name' => 'System-Agent',
            'description' => 'Wiederkehrender automatisierter Funktionstraeger mit eigenem Heartbeat. Fuellt VSM-Funktionen aus (typisch S2, S3, S3*, S4). Wird als Entity im Baum gepflegt, Parent = Knoten dessen Kontext er bedient. Inference-Prompts haengen via FK agent_entity_id N:1 an einem Agent — alle Prompts eines Agents fuellen die gleiche VSM-Ebene aus.',
            'icon' => 'cpu-chip',
            'sort_order' => 8,
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
        DB::table('organization_entity_types')
            ->where('code', 'system_agent')
            ->delete();
    }
};

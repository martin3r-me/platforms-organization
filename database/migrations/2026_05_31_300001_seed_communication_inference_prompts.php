<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        $teams = Team::pluck('id');

        $prompts = [
            [
                'name' => 'Kommunikationsstille erkennen',
                'description' => 'Identifiziert Entities ohne eingehende Correspondence seit >14 Tagen — Schweigen als Auditsignal.',
                'vsm_system' => 's3_star',
                'prompt_template' => 'Prüfe die Correspondence-Daten der Entities. Welche Entities haben seit mehr als 14 Tagen keine eingehende Korrespondenz? Stille kann bedeuten: abgekoppelt, vergessen, oder bewusst autonom. Unterscheide zwischen besorgniserregender Stille (Entity sollte aktiv kommunizieren basierend auf ihrer Rolle) und akzeptabler Stille (Entity ist stabil und braucht keinen Austausch). Erstelle nur Signale für besorgniserregende Fälle.',
                'data_sources' => ['correspondence', 'snapshots'],
                'dimension' => 'quality',
                'default_severity' => 'warning',
                'scope_type' => 'all',
                'is_active' => true,
                'schedule_interval_hours' => 72,
            ],
            [
                'name' => 'Correspondence ohne Wirkung',
                'description' => 'Erkennt Entities mit hohem Correspondence-Aufkommen aber ohne korrelierende Aktionen oder Bewegung.',
                'vsm_system' => 's2',
                'prompt_template' => 'Analysiere die Verbindung zwischen Correspondence-Aktivität und tatsächlicher Bewegung der Entities. Gibt es Entities die viel Korrespondenz haben (correspondence_total, correspondence_this_week) aber keine korrelierenden Veränderungen in anderen Metriken zeigen (z.B. Planner-Items, Throughput, Runs)? Hohe Kommunikation ohne Wirkung kann auf Koordinationsprobleme, Blockaden oder ineffektive Abstimmung hinweisen. Erstelle Signale nur wenn das Missverhältnis deutlich ist.',
                'data_sources' => ['correspondence', 'snapshots', 'movement'],
                'dimension' => 'energy',
                'default_severity' => 'info',
                'scope_type' => 'all',
                'is_active' => true,
                'schedule_interval_hours' => 168,
            ],
            [
                'name' => 'Strategische Themen in Correspondence',
                'description' => 'Erkennt neue strategische Herausforderungen oder Chancen aus dem Correspondence-Verlauf.',
                'vsm_system' => 's4',
                'prompt_template' => 'Analysiere die Correspondence-Daten und Umwelt-Snapshots der Entities. Gibt es wiederkehrende Themen in der Korrespondenz die auf neue strategische Herausforderungen oder Chancen hindeuten? Suche nach Mustern wie: häufige Erwähnung neuer Anforderungen, wiederkehrende Beschwerden, Hinweise auf Marktveränderungen, oder Signale dass bestehende Prozesse nicht mehr passen. Vergleiche mit den Environment-Snapshots um externe Trends zu korrelieren.',
                'data_sources' => ['correspondence', 'environment'],
                'dimension' => 'potential',
                'default_severity' => 'info',
                'scope_type' => 'all',
                'is_active' => true,
                'schedule_interval_hours' => 168,
            ],
        ];

        foreach ($teams as $teamId) {
            foreach ($prompts as $prompt) {
                DB::table('organization_signal_inference_prompts')->insert(array_merge($prompt, [
                    'uuid' => UuidV7::generate(),
                    'team_id' => $teamId,
                    'user_id' => null,
                    'scope_value' => null,
                    'last_evaluated_at' => null,
                    'data_sources' => json_encode($prompt['data_sources']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('organization_signal_inference_prompts')
            ->whereIn('name', [
                'Kommunikationsstille erkennen',
                'Correspondence ohne Wirkung',
                'Strategische Themen in Correspondence',
            ])
            ->delete();
    }
};

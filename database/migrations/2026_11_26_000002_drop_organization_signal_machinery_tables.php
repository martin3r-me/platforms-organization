<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Signale, Inferenz, Rückfragen, Synthese und Memory liegen nicht mehr in der Organisation,
 * sondern im Modul Signals (Neustart ohne Datenübernahme). Die Tabellen der alten Maschine
 * entfallen. Reihenfolge: Kinder zuerst, damit Fremdschlüssel nicht blockieren.
 * Entity-Snapshots bleiben — Signals liest sie.
 */
return new class extends Migration
{
    private const TABLES = [
        // hängen an organization_signals
        'organization_signal_comments',
        'organization_signal_actions',
        'organization_signal_focuses',
        // hängen an Inferenz-Läufen / -Prompts
        'organization_inference_run_steps',
        'organization_inference_prompt_stats',
        // Rückfragen: Empfänger vor Rückfrage
        'organization_inquiry_recipients',
        'organization_inquiries',
        'organization_memory_entries',
        // Signale (FK auf Definitionen, Prompts, sich selbst)
        'organization_signals',
        // Synthese
        'organization_synthesis_reports',
        'organization_synthesis_prompt_definitions',
        // Inferenz-Läufe und Trigger
        'organization_inference_runs',
        'organization_inference_triggers',
        // Wurzeln
        'organization_signal_definitions',
        'organization_signal_inference_prompts',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABLES as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // bewusst leer – vor dem Drop wurde ein Dump der Tabellen gezogen
    }
};

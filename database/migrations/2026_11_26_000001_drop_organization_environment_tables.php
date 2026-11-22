<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Umwelt-Feeds liegen nicht mehr in der Organisation (eigenes Modul, Agenten lesen per MCP).
 * Quellen werden vorher dorthin übernommen; die Snapshots waren LLM-Zusammenfassungen und entfallen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_environment_snapshots');
        Schema::dropIfExists('organization_environment_sources');
    }

    public function down(): void
    {
        // bewusst leer – die Daten sind übernommen bzw. verworfen
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert organization_entity_relation_types um Property-basierte
 * Channel-Semantik (Beer-VSM).
 *
 * Snapshot- und Movement-Services treffen Aggregations-Entscheidungen rein
 * anhand dieser Properties — kein Hardcoding auf relation_type_code im Service.
 *
 * Beer-Theorie:
 *  - relation_types werden hier zu typisierten Channels (Resource Bargain,
 *    Anti-Oscillatory, Algedonic, etc.) entlang ihrer Properties.
 *  - parent_entity_id bleibt der recursive containment channel (Tree).
 *  - Relations bilden alle anderen Channel-Klassen ab.
 *
 * Idempotent: Migration darf mehrfach laufen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_entity_relation_types', function (Blueprint $table) {
            // --- Aggregations-Verhalten (Snapshot/Movement) -----------------

            if (! Schema::hasColumn('organization_entity_relation_types', 'affects_aggregation')) {
                // Treibt Snapshot/Movement-Traversal. Default false = reine Info-Beziehung.
                $table->boolean('affects_aggregation')->default(false)->after('is_reciprocal');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'is_recursive')) {
                // Mehrere Hops folgen (transitiv) statt nur direkter Hit.
                $table->boolean('is_recursive')->default(false)->after('affects_aggregation');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'cascade_to_children')) {
                // Sub-Entities der Quell-Entity erben die Relation (z.B. Projekt-Subtasks
                // erben das Engagement der Projekt-Mutter).
                $table->boolean('cascade_to_children')->default(false)->after('is_recursive');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'aggregation_weight')) {
                // Variety-Gewicht. 1.0 = volle Stärke. Werte < 1.0 = abgeschwächter
                // Variety-Beitrag (z.B. nur 0.5 fuer informelle Channels).
                $table->decimal('aggregation_weight', 5, 4)->default(1.0)->after('cascade_to_children');
            }

            // --- Richtungs-Semantik -----------------------------------------

            if (! Schema::hasColumn('organization_entity_relation_types', 'traversal_direction')) {
                // forward = from→to, reverse = to→from (umgekehrt traversieren),
                // both = beide Richtungen relevant fuer Aggregation.
                $table->enum('traversal_direction', ['forward', 'reverse', 'both'])
                    ->default('forward')->after('aggregation_weight');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'inverse_code')) {
                // Explizite Umkehr-Type (z.B. 'engagement_with' ↔ 'engaged_by').
                // Wenn null und is_directional=false, gilt der Type als symmetrisch.
                $table->string('inverse_code', 100)->nullable()->after('traversal_direction');
            }

            // --- Validierung & Typen-Konstraints ----------------------------

            if (! Schema::hasColumn('organization_entity_relation_types', 'allowed_from_types')) {
                // JSON-Array mit erlaubten Entity-Type-Codes als Quelle. null = alle erlaubt.
                $table->json('allowed_from_types')->nullable()->after('inverse_code');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'allowed_to_types')) {
                // JSON-Array mit erlaubten Entity-Type-Codes als Ziel. null = alle erlaubt.
                $table->json('allowed_to_types')->nullable()->after('allowed_from_types');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'cardinality')) {
                // Multiplizitaets-Constraint: '1:1' | '1:N' | 'N:M'.
                $table->enum('cardinality', ['1:1', '1:N', 'N:M'])
                    ->default('N:M')->after('allowed_to_types');
            }

            // --- Beer-Theorie-Anker -----------------------------------------

            if (! Schema::hasColumn('organization_entity_relation_types', 'channel_class')) {
                // Beer-Channel-Klassen:
                //  - operational: Resource Bargain, Steuerung, Outcome
                //  - informational: Lookup, Hinweis, Beziehung (keine Variety)
                //  - structural: Tree-aequivalente Containment
                //  - algedonic: Notruf-Channel ("schreit um Hilfe")
                //  - environmental: Umwelt-Probe (S4-Channel)
                $table->enum('channel_class', [
                    'operational', 'informational', 'structural', 'algedonic', 'environmental',
                ])->nullable()->after('cardinality');
            }

            if (! Schema::hasColumn('organization_entity_relation_types', 'variety_flow')) {
                // Wer reichert wessen Variety an:
                //  - from_to: Quelle reichert Ziel an
                //  - to_from: Ziel reichert Quelle an
                //  - bidirectional: beide
                //  - none: kein Variety-Fluss (reiner Info-Link)
                $table->enum('variety_flow', ['from_to', 'to_from', 'bidirectional', 'none'])
                    ->default('none')->after('channel_class');
            }

            // --- Extensibility ----------------------------------------------

            if (! Schema::hasColumn('organization_entity_relation_types', 'capabilities')) {
                // Frei taggbare Capability-Tags fuer zukuenftige Erweiterungen
                // (z.B. ['supports_billing', 'requires_approval', 'triggers_workflow']).
                // Service-Code darf diese Tags inspizieren ohne Schema-Migration.
                $table->json('capabilities')->nullable()->after('variety_flow');
            }
        });

        // --- Indizes fuer typische Aggregations-Queries ---------------------

        Schema::table('organization_entity_relation_types', function (Blueprint $table) {
            $existing = $this->existingIndexes('organization_entity_relation_types');

            if (! in_array('org_entity_rel_types_aggregation_idx', $existing, true)) {
                $table->index(['affects_aggregation', 'is_active'], 'org_entity_rel_types_aggregation_idx');
            }

            if (! in_array('org_entity_rel_types_channel_class_idx', $existing, true)) {
                $table->index(['channel_class', 'is_active'], 'org_entity_rel_types_channel_class_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_entity_relation_types', function (Blueprint $table) {
            $existing = $this->existingIndexes('organization_entity_relation_types');

            if (in_array('org_entity_rel_types_aggregation_idx', $existing, true)) {
                $table->dropIndex('org_entity_rel_types_aggregation_idx');
            }
            if (in_array('org_entity_rel_types_channel_class_idx', $existing, true)) {
                $table->dropIndex('org_entity_rel_types_channel_class_idx');
            }

            foreach ([
                'capabilities',
                'variety_flow',
                'channel_class',
                'cardinality',
                'allowed_to_types',
                'allowed_from_types',
                'inverse_code',
                'traversal_direction',
                'aggregation_weight',
                'cascade_to_children',
                'is_recursive',
                'affects_aggregation',
            ] as $col) {
                if (Schema::hasColumn('organization_entity_relation_types', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    protected function existingIndexes(string $table): array
    {
        $rows = \DB::select("SHOW INDEX FROM `{$table}`");
        return array_unique(array_map(fn ($r) => $r->Key_name, $rows));
    }
};

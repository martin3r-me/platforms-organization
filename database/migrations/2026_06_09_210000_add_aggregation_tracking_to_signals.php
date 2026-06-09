<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 5c: Aggregations-Tracking am Signal.
 *
 * Wenn ein Signal in der inneren Perspektive nicht absorbiert wird (auf S5
 * eskaliert, weiter unbeachtet), erzeugt der Eskalations-Cron ein neues
 * Signal in der aeusseren Perspektive (Parent-Carrier) mit
 * source_type='aggregation'. Damit das Original nicht jeden Lauf erneut
 * aggregiert wird, markieren wir es:
 *
 *  - aggregated_at: wann wurde es nach oben aggregiert
 *  - aggregated_to_signal_id: auf welches outer-Signal verweist es
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->timestamp('aggregated_at')->nullable()->after('acknowledged_at');
            $table->unsignedBigInteger('aggregated_to_signal_id')->nullable()->after('aggregated_at');

            $table->foreign('aggregated_to_signal_id', 'signal_aggregated_to_fk')
                ->references('id')->on('organization_signals')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('aggregated_at', 'signal_aggregated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            try { $table->dropForeign('signal_aggregated_to_fk'); } catch (\Throwable $e) {}
            try { $table->dropIndex('signal_aggregated_at_idx'); } catch (\Throwable $e) {}
            $table->dropColumn(['aggregated_at', 'aggregated_to_signal_id']);
        });
    }
};

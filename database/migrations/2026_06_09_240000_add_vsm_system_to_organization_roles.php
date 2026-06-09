<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen koennen optional einer VSM-Funktion (S1..S5, S3*) entsprechen.
 *
 * Beer-Begruendung: dieselbe Person traegt mehrere VSM-Funktionen durch
 * ihre Rollen — als Inhaber S5, als GF S3, als Senior-Engineer S1. Die
 * Funktion gehoert nicht an die Person, sondern an die Rolle, die die
 * Person ausuebt.
 *
 * vsm_system ist nullable: Rollen ohne VSM-Bedeutung (z.B. rein
 * organisatorische Tags wie "Lehrling") bleiben erlaubt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->string('vsm_system', 16)->nullable()->after('description');
            $table->index('vsm_system', 'org_roles_vsm_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organization_roles', function (Blueprint $table) {
            $table->dropIndex('org_roles_vsm_idx');
            $table->dropColumn('vsm_system');
        });
    }
};

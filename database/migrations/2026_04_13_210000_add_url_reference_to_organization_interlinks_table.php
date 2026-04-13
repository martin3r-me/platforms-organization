<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_interlinks', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('description');
            $table->string('reference', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('organization_interlinks', function (Blueprint $table) {
            $table->dropColumn(['url', 'reference']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('hourly_rate');
            $table->timestamp('public_token_expires_at')->nullable()->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'public_token_expires_at']);
        });
    }
};

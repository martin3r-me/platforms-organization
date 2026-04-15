<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->string('process_category')->nullable()->after('status');
            $table->boolean('is_focus')->default(false)->after('process_category');
            $table->text('focus_reason')->nullable()->after('is_focus');
            $table->date('focus_until')->nullable()->after('focus_reason');
        });
    }

    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table) {
            $table->dropColumn(['process_category', 'is_focus', 'focus_reason', 'focus_until']);
        });
    }
};

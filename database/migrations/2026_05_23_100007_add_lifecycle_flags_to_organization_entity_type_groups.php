<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_entity_type_groups', function (Blueprint $table) {
            $table->boolean('allow_soft_delete')->default(true)->after('is_active');
            $table->boolean('allow_rename')->default(true)->after('allow_soft_delete');
            $table->boolean('allow_merge')->default(false)->after('allow_rename');
            $table->boolean('allow_split')->default(false)->after('allow_merge');
        });
    }

    public function down(): void
    {
        Schema::table('organization_entity_type_groups', function (Blueprint $table) {
            $table->dropColumn(['allow_soft_delete', 'allow_rename', 'allow_merge', 'allow_split']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_process_id')->nullable()->after('corefit_classification');

            $table->foreign('sub_process_id')
                ->references('id')
                ->on('organization_processes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_process_steps', function (Blueprint $table) {
            $table->dropForeign(['sub_process_id']);
            $table->dropColumn('sub_process_id');
        });
    }
};

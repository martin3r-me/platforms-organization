<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_inquiries', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_inquiry_id')->nullable()->after('follow_up_signal_id');
            $table->unsignedTinyInteger('depth')->default(0)->after('parent_inquiry_id');

            $table->index('parent_inquiry_id');
            $table->foreign('parent_inquiry_id')
                ->references('id')
                ->on('organization_inquiries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_inquiries', function (Blueprint $table) {
            $table->dropForeign(['parent_inquiry_id']);
            $table->dropIndex(['parent_inquiry_id']);
            $table->dropColumn(['parent_inquiry_id', 'depth']);
        });
    }
};

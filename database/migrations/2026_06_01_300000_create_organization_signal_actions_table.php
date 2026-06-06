<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signal_actions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('signal_id')->constrained('organization_signals')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            // pending | applied | dismissed
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['signal_id', 'position'], 'sigact_signal_position');
            $table->index(['signal_id', 'status'], 'sigact_signal_status');
        });

        // Backfill: materialize existing suggested_actions JSON into rows
        DB::table('organization_signals')
            ->whereNotNull('suggested_actions')
            ->orderBy('id')
            ->select(['id', 'suggested_actions'])
            ->chunkById(200, function ($signals) {
                $now = now();
                $rows = [];

                foreach ($signals as $signal) {
                    $actions = json_decode($signal->suggested_actions, true);
                    if (! is_array($actions) || empty($actions)) {
                        continue;
                    }

                    foreach (array_values($actions) as $idx => $action) {
                        $title = trim((string) ($action['title'] ?? ''));
                        if ($title === '') {
                            continue;
                        }

                        $rows[] = [
                            'uuid' => UuidV7::generate(),
                            'signal_id' => $signal->id,
                            'position' => $idx,
                            'title' => mb_substr($title, 0, 255),
                            'description' => $action['description'] ?? null,
                            'status' => 'pending',
                            'decision_reason' => null,
                            'decided_by' => null,
                            'decided_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (! empty($rows)) {
                    DB::table('organization_signal_actions')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signal_actions');
    }
};

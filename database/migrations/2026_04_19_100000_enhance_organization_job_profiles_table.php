<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->text('purpose')->nullable()->after('description');
            $table->string('job_family')->nullable()->after('purpose');
            $table->json('requirements')->nullable()->after('responsibilities');
            $table->json('soft_skills')->nullable()->after('requirements');
            $table->json('kpis')->nullable()->after('soft_skills');
        });

        // Migrate skills from flat array ["PHP", "Laravel"] to structured [{"name": "PHP", "level": "expert", "category": "technical"}]
        DB::table('organization_job_profiles')
            ->whereNotNull('skills')
            ->orderBy('id')
            ->each(function ($row) {
                $skills = json_decode($row->skills, true);
                if (! is_array($skills)) {
                    return;
                }
                // Skip if already structured (first element is an object)
                if (isset($skills[0]) && is_array($skills[0])) {
                    return;
                }
                $structured = array_map(fn (string $skill) => [
                    'name' => trim($skill),
                    'level' => 'expert',
                    'category' => 'technical',
                ], $skills);

                DB::table('organization_job_profiles')
                    ->where('id', $row->id)
                    ->update(['skills' => json_encode($structured)]);
            });

        // Migrate responsibilities from flat ["Code Reviews"] to [{"name": "Code Reviews", "is_core": true}]
        DB::table('organization_job_profiles')
            ->whereNotNull('responsibilities')
            ->orderBy('id')
            ->each(function ($row) {
                $responsibilities = json_decode($row->responsibilities, true);
                if (! is_array($responsibilities)) {
                    return;
                }
                if (isset($responsibilities[0]) && is_array($responsibilities[0])) {
                    return;
                }
                $structured = array_map(fn (string $r) => [
                    'name' => trim($r),
                    'is_core' => true,
                ], $responsibilities);

                DB::table('organization_job_profiles')
                    ->where('id', $row->id)
                    ->update(['responsibilities' => json_encode($structured)]);
            });
    }

    public function down(): void
    {
        // Revert skills back to flat format
        DB::table('organization_job_profiles')
            ->whereNotNull('skills')
            ->orderBy('id')
            ->each(function ($row) {
                $skills = json_decode($row->skills, true);
                if (! is_array($skills) || empty($skills)) {
                    return;
                }
                if (isset($skills[0]) && is_array($skills[0])) {
                    $flat = array_map(fn ($s) => $s['name'] ?? '', $skills);
                    DB::table('organization_job_profiles')
                        ->where('id', $row->id)
                        ->update(['skills' => json_encode(array_values(array_filter($flat)))]);
                }
            });

        // Revert responsibilities back to flat format
        DB::table('organization_job_profiles')
            ->whereNotNull('responsibilities')
            ->orderBy('id')
            ->each(function ($row) {
                $responsibilities = json_decode($row->responsibilities, true);
                if (! is_array($responsibilities) || empty($responsibilities)) {
                    return;
                }
                if (isset($responsibilities[0]) && is_array($responsibilities[0])) {
                    $flat = array_map(fn ($r) => $r['name'] ?? '', $responsibilities);
                    DB::table('organization_job_profiles')
                        ->where('id', $row->id)
                        ->update(['responsibilities' => json_encode(array_values(array_filter($flat)))]);
                }
            });

        Schema::table('organization_job_profiles', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'job_family', 'requirements', 'soft_skills', 'kpis']);
        });
    }
};

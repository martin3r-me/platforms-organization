<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $jobProfiles = DB::table('organization_job_profiles')
            ->whereNotNull('skills')
            ->orWhereNotNull('soft_skills')
            ->get();

        foreach ($jobProfiles as $jp) {
            $teamId = $jp->team_id;

            // Migrate skills
            $skills = json_decode($jp->skills, true);
            if (is_array($skills)) {
                $sortOrder = 0;
                foreach ($skills as $skillData) {
                    $name = trim($skillData['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $category = $skillData['category'] ?? 'technical';
                    if (! in_array($category, ['technical', 'methodical', 'domain'])) {
                        $category = 'technical';
                    }

                    $level = $skillData['level'] ?? 'expert';
                    if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                        $level = 'expert';
                    }

                    // firstOrCreate skill in catalog
                    $existing = DB::table('organization_skills')
                        ->where('team_id', $teamId)
                        ->where('name', $name)
                        ->first();

                    if ($existing) {
                        $skillId = $existing->id;
                    } else {
                        $skillId = DB::table('organization_skills')->insertGetId([
                            'uuid'       => (string) \Symfony\Component\Uid\UuidV7::generate(),
                            'team_id'    => $teamId,
                            'name'       => $name,
                            'category'   => $category,
                            'is_active'  => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Insert pivot (ignore duplicates)
                    $pivotExists = DB::table('organization_job_profile_skills')
                        ->where('job_profile_id', $jp->id)
                        ->where('skill_id', $skillId)
                        ->exists();

                    if (! $pivotExists) {
                        DB::table('organization_job_profile_skills')->insert([
                            'job_profile_id' => $jp->id,
                            'skill_id'       => $skillId,
                            'level'          => $level,
                            'is_required'    => true,
                            'sort_order'     => $sortOrder++,
                        ]);
                    }
                }
            }

            // Migrate soft skills
            $softSkills = json_decode($jp->soft_skills, true);
            if (is_array($softSkills)) {
                $sortOrder = 0;
                foreach ($softSkills as $ssData) {
                    $name = trim($ssData['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $level = $ssData['level'] ?? 'expert';
                    if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                        $level = 'expert';
                    }

                    // firstOrCreate soft skill in catalog
                    $existing = DB::table('organization_soft_skills')
                        ->where('team_id', $teamId)
                        ->where('name', $name)
                        ->first();

                    if ($existing) {
                        $softSkillId = $existing->id;
                    } else {
                        $softSkillId = DB::table('organization_soft_skills')->insertGetId([
                            'uuid'       => (string) \Symfony\Component\Uid\UuidV7::generate(),
                            'team_id'    => $teamId,
                            'name'       => $name,
                            'is_active'  => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Insert pivot (ignore duplicates)
                    $pivotExists = DB::table('organization_job_profile_soft_skills')
                        ->where('job_profile_id', $jp->id)
                        ->where('soft_skill_id', $softSkillId)
                        ->exists();

                    if (! $pivotExists) {
                        DB::table('organization_job_profile_soft_skills')->insert([
                            'job_profile_id' => $jp->id,
                            'soft_skill_id'  => $softSkillId,
                            'level'          => $level,
                            'is_required'    => true,
                            'sort_order'     => $sortOrder++,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Data migration - no rollback needed as JSON columns remain intact
    }
};

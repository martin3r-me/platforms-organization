<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationAgentProfile;
use Platform\Organization\Models\OrganizationRoleAssignment;

/**
 * Die Runtime-Config eines Agenten, wie der Daemon sie über GET /api/org/agent/profile zieht.
 * EINE Quelle für die API und das Lese-Tool (organization.agent_profile.GET) — was ein Mensch per
 * MCP sieht, ist genau das, was der Agent bekommt.
 */
class AgentRuntimeProfile
{
    public function build(OrganizationAgentProfile $profile): array
    {
        // Stufen (Dev-Loop-Konfiguration) aus den Rollen-Assignments ableiten. Die „Domäne" als
        // Verhaltens-Treiber ist abgeschafft — was der Agent TUT, kommt aus dem Job-Profil (unten)
        // + seinen Capabilities/Tokens.
        $stages = OrganizationRoleAssignment::query()
            ->where('person_entity_id', $profile->organization_entity_id)
            ->with('role')
            ->get()
            ->pluck('role')
            ->filter()
            ->pluck('stage')->filter()->unique()->values()->all();

        $jobProfile = $this->jobProfileForEntity((int) $profile->organization_entity_id);
        // BEZUGSPERSON aus der Struktur (Berichtet an → Leitung der Einheit → Job-Profil). Der Daemon
        // übernimmt sie statt SUPERVISOR aus seiner Env.
        $supervisor = $profile->entity
            ? app(AgentSupervisor::class)->resolve($profile->entity, $jobProfile['reporting']['reports_to'] ?? null)
            : null;

        return [
            'stages' => $stages,
            'active' => (bool) $profile->active,
            'governor' => [
                'five_hour_reserve_pct' => $profile->five_hour_reserve_pct,
                'seven_day_burn_margin_pct' => $profile->seven_day_burn_margin_pct,
            ],
            'max_story_points' => $profile->max_story_points,
            'claude_model' => $profile->claude_model,
            'claim_unassigned' => (bool) $profile->claim_unassigned,
            'github_username' => $profile->github_username,
            'settings' => $profile->settings ?? [],
            // Die Stellenbeschreibung als Org-Daten: purpose + responsibilities (= die Routinen der
            // Loop) + kpis + reporting. Weich referenziert (People baut auf Organization auf, nicht
            // umgekehrt) — fehlt das People-Modul oder das Profil, bleibt der Schlüssel null.
            'job_profile' => $jobProfile,
            'supervisor' => $supervisor['text'] ?? null,
            'supervisor_ref' => $supervisor,
            // ART-PRIOR (empirical Bayes, leave-one-out): wie kalibriert die ANDEREN Agent-Mitglieder
            // im Schnitt sind. Ein neues Mitglied erbt diesen Instinkt bei der Geburt, bis es eigene
            // Daten hat (VSM: die Organisation lernt über ihre Mitglieder und prägt die neuen).
            'calibration_prior' => $this->calibrationPrior((int) $profile->organization_entity_id),
        ];
    }

    /**
     * calibrationPrior — der ART-PRIOR aus der Population: n-gewichteter Durchschnitt der
     * Kalibrierung ALLER ANDEREN aktiven Agent-Mitglieder (leave-one-out, sonst zählt der Agent
     * seine eigenen Daten doppelt). Nur frische Meldungen (30 Tage). null = noch keine Population.
     */
    private function calibrationPrior(int $excludeEntityId): ?array
    {
        $fleet = OrganizationAgentProfile::query()
            ->where('active', true)
            ->where('organization_entity_id', '!=', $excludeEntityId)
            ->where('calib_n', '>', 0)
            ->whereNotNull('calib_gap')
            ->where('calib_updated_at', '>', now()->subDays(30))
            ->get(['calib_n', 'calib_mean_conf', 'calib_accuracy', 'calib_gap']);

        $totalN = (int) $fleet->sum('calib_n');
        if ($totalN < 1) {
            return null;
        }
        $w = fn (string $col) => $fleet->sum(fn ($p) => $p->calib_n * (float) $p->$col) / $totalN;

        return [
            'n' => $totalN,
            'agents' => $fleet->count(),
            'mean_conf' => round($w('calib_mean_conf'), 4),
            'accuracy' => round($w('calib_accuracy'), 4),
            'gap' => round($w('calib_gap'), 4),
        ];
    }

    /**
     * Weiche Auflösung der Stellenbeschreibung eines Agenten über die Org-Entity-Kante
     * (people_job_profiles.owner_entity_id = organization_entities.id). Tolerant: fehlende
     * People-Klasse oder kein aktives Profil → null (kein harter Abbruch, kein Cross-Modul-Zwang).
     */
    private function jobProfileForEntity(int $entityId): ?array
    {
        if ($entityId < 1) {
            return null;
        }
        $class = \Platform\People\Models\JobProfile::class;
        if (! class_exists($class)) {
            return null;
        }
        try {
            /** @var \Platform\People\Models\JobProfile|null $jp */
            $jp = $class::query()
                ->where('owner_entity_id', $entityId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (! $jp) {
            return null;
        }

        return [
            'id' => $jp->id,
            'name' => $jp->name,
            'level' => $jp->level,
            // VSM-Funktions-Stufe(n) der Rolle (Liste, z.B. ["S2","S3","S3star"]). Der Daemon gated
            // darüber rollen-gescopte Sinne (Pulse nur für S2↑).
            'vsm_level' => $jp->vsm_level ?? [],
            'purpose' => $jp->purpose,
            // responsibilities = die wiederkehrenden Pflichten → werden im Daemon zu Routinen der Loop.
            'responsibilities' => $jp->responsibilities ?? [],
            'kpis' => $jp->kpis ?? [],
            'reporting' => $jp->reporting ?? [],
        ];
    }
}

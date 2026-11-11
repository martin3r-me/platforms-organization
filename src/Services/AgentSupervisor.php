<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationship;

/**
 * Die Bezugsperson (Vorgesetzte/r) eines Agenten — aus der Organisationsstruktur abgeleitet statt in der
 * Container-Konfiguration hinterlegt. Reihenfolge:
 *   1. Beziehung „Berichtet an“ (reports_to) vom Agenten zu einer Person — oder zu einer Einheit, dann
 *      deren Leitung;
 *   2. eine Person, die den Agenten „führt“ (manages);
 *   3. die Leitung der nächsten übergeordneten Einheit (wer sie „führt“, sonst ihr verantwortlicher User);
 *   4. die Berichtslinie aus dem Job-Profil (Freitext-Name, auf eine Person aufgelöst).
 */
class AgentSupervisor
{
    private const SOURCES = [
        'reports_to' => 'Beziehung „Berichtet an“',
        'reports_to_unit' => 'Leitung der Einheit, an die der Agent berichtet',
        'manages' => 'Beziehung „Führt an“',
        'parent_unit' => 'Leitung der übergeordneten Einheit',
        'job_profile' => 'Berichtslinie im Job-Profil',
    ];

    /** @return array{name:string,email:?string,entity_id:int,source:string,source_label:string,text:string}|null */
    public function resolve(OrganizationEntity $agent, ?string $jobProfileReportsTo = null): ?array
    {
        $reportsTo = $this->typeId('reports_to');
        $manages = $this->typeId('manages');

        if ($reportsTo) {
            $rel = OrganizationEntityRelationship::query()->active()
                ->where('from_entity_id', $agent->id)->where('relation_type_id', $reportsTo)
                ->with('toEntity')->latest('id')->first();
            if ($rel?->toEntity) {
                if ($this->isPerson($rel->toEntity)) {
                    return $this->out($rel->toEntity, 'reports_to');
                }
                if ($lead = $this->leaderOf($rel->toEntity, $manages)) {
                    return $this->out($lead, 'reports_to_unit');
                }
            }
        }

        if ($manages) {
            $rel = OrganizationEntityRelationship::query()->active()
                ->where('to_entity_id', $agent->id)->where('relation_type_id', $manages)
                ->with('fromEntity')->latest('id')->first();
            if ($rel?->fromEntity && $this->isPerson($rel->fromEntity)) {
                return $this->out($rel->fromEntity, 'manages');
            }
        }

        $unit = $agent->parent;
        for ($depth = 0; $unit && $depth < 12; $depth++) {
            if ($lead = $this->leaderOf($unit, $manages)) {
                return $this->out($lead, 'parent_unit');
            }
            $unit = $unit->parent;
        }

        $name = trim((string) $jobProfileReportsTo);
        if ($name !== '') {
            $person = OrganizationEntity::query()->persons()
                ->where('team_id', $agent->team_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if ($person) {
                return $this->out($person, 'job_profile');
            }
        }

        return null;
    }

    /** Leitung einer Einheit: wer sie „führt“, sonst ihr verantwortlicher User (als Person-Entität). */
    private function leaderOf(OrganizationEntity $unit, ?int $manages): ?OrganizationEntity
    {
        if ($manages) {
            $rel = OrganizationEntityRelationship::query()->active()
                ->where('to_entity_id', $unit->id)->where('relation_type_id', $manages)
                ->with('fromEntity')->latest('id')->first();
            if ($rel?->fromEntity && $this->isPerson($rel->fromEntity)) {
                return $rel->fromEntity;
            }
        }
        if ($unit->linked_user_id && ! $this->isPerson($unit)) {
            $person = OrganizationEntity::query()->persons()->where('linked_user_id', $unit->linked_user_id)->first();
            if ($person) {
                return $person;
            }
        }

        return null;
    }

    private function isPerson(OrganizationEntity $e): bool
    {
        return $e->type?->code === 'person';
    }

    private function typeId(string $code): ?int
    {
        return OrganizationEntityRelationType::query()->where('code', $code)->value('id');
    }

    private function out(OrganizationEntity $person, string $source): array
    {
        $email = $person->linkedUser?->email ? mb_strtolower($person->linkedUser->email) : null;

        return [
            'name' => $person->name,
            'email' => $email,
            'entity_id' => (int) $person->id,
            'source' => $source,
            'source_label' => self::SOURCES[$source] ?? $source,
            // Die Form, die der Daemon versteht („Name (E-Mail)“ — der Handle wird daraus gelesen).
            'text' => $email ? "{$person->name} ({$email})" : $person->name,
        ];
    }
}

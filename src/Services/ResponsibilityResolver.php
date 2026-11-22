<?php

namespace Platform\Organization\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Platform\Core\Authz\Capability;
use Platform\Organization\Contracts\AvailabilityProvider;
use Platform\Organization\Models\OrganizationAgentProfile;
use Platform\Organization\Models\OrganizationDeputy;
use Platform\Organization\Models\OrganizationEntity;

/**
 * „Wer ist jetzt verantwortlich für Knoten X mit Fähigkeit C?“ – eine Antwort für alle Module.
 *
 * Verantwortlich ist, wer am Knoten eine Beziehung (Person/Agent → Knoten) oder Rolle hält, deren Fähigkeiten
 * C erfüllen (dieselbe Quelle wie authz:materialize). Reihenfolge nach Rang (responsibility_rank, kleiner zuerst).
 * Abwesende werden durch ihre Vertretung ersetzt; ohne Vertretung rückt der Nächste im Rang am selben Knoten nach.
 * Ohne Verantwortliche am Knoten geht die Suche den Baum hinauf.
 */
class ResponsibilityResolver
{
    private const NO_RANK = 1000;

    /** @var array<int, ?array> */
    private array $absenceCache = [];

    /**
     * Verantwortliche direkt an einem Knoten, geordnet, Abwesenheit und Vertretung berücksichtigt.
     *
     * @param  list<int>  $excludeEntityIds  befangene Personen (z. B. der Gegenstand des Signals)
     * @return list<array<string,mixed>>
     */
    public function holdersAt(int $nodeId, string $capability = 'manage', array $excludeEntityIds = [], ?DateTimeInterface $at = null): array
    {
        $at = CarbonImmutable::instance($at ?? now());
        $exclude = array_flip(array_map('intval', $excludeEntityIds));

        $raw = $this->rawHolders($nodeId, $capability, $at);
        $out = [];
        $absent = [];
        foreach ($raw as $h) {
            if (isset($exclude[$h['entity_id']])) {
                continue;
            }
            $absence = $this->absenceOf($h['entity_id'], $h['kind'], $at);
            if (! $absence) {
                $out[$h['entity_id']] ??= $h + ['reason' => $this->viaText($h)];
            } else {
                $absent[] = $h + ['absence' => $absence];
            }
            // generelle Vertretung: Vertreter ist immer mit dabei
            foreach ($this->deputiesFor($h['entity_id'], $nodeId, $at, false) as $d) {
                if (! isset($exclude[$d['entity_id']]) && ! $this->absenceOf($d['entity_id'], $d['kind'], $at)) {
                    $out[$d['entity_id']] ??= $d + ['rank' => $h['rank'], 'via' => 'deputy', 'via_code' => null, 'via_name' => 'Vertretung',
                        'node_entity_id' => $nodeId, 'deputy_for' => ['entity_id' => $h['entity_id'], 'name' => $h['name']],
                        'reason' => "vertritt {$h['name']} generell"];
                }
            }
        }

        // Abwesende: Vertretung bei Abwesenheit, sonst rückt der Nächste im Rang am Knoten nach (schon in $out)
        foreach ($absent as $h) {
            foreach ($this->deputiesFor($h['entity_id'], $nodeId, $at, true) as $d) {
                if (isset($exclude[$d['entity_id']]) || $this->absenceOf($d['entity_id'], $d['kind'], $at)) {
                    continue;
                }
                $until = $h['absence']['until'] ? ' bis '.CarbonImmutable::parse($h['absence']['until'])->format('d.m.') : '';
                $out[$d['entity_id']] ??= $d + ['rank' => $h['rank'], 'via' => 'deputy', 'via_code' => null, 'via_name' => 'Vertretung',
                    'node_entity_id' => $nodeId, 'deputy_for' => ['entity_id' => $h['entity_id'], 'name' => $h['name']],
                    'reason' => "vertritt {$h['name']} (abwesend{$until})"];
            }
        }

        $out = array_values($out);
        usort($out, fn ($a, $b) => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']]);

        return array_map(fn ($h) => $h + ['supervisor' => $h['kind'] === 'agent' ? $this->supervisorOf($h['entity_id']) : null], $out);
    }

    /**
     * Der nächste Verantwortliche ab einem Knoten aufwärts.
     *
     * @return array{entity_id:int, found:bool, node:?array{id:int,name:string}, distance:?int, holders:list<array>, path:list<array{id:int,name:string,reason:string}>}
     */
    public function responsibleFor(int $entityId, string $capability = 'manage', array $excludeEntityIds = [], ?DateTimeInterface $at = null): array
    {
        $path = [];
        $seen = [];
        $node = OrganizationEntity::query()->find($entityId);
        $distance = 0;
        while ($node && ! isset($seen[$node->id])) {
            $seen[$node->id] = true;
            $holders = $this->holdersAt($node->id, $capability, $excludeEntityIds, $at);
            if ($holders) {
                return ['entity_id' => $entityId, 'found' => true, 'node' => ['id' => $node->id, 'name' => $node->name], 'distance' => $distance, 'holders' => $holders, 'path' => $path];
            }
            $raw = $this->rawHolders($node->id, $capability, CarbonImmutable::instance($at ?? now()));
            $path[] = ['id' => $node->id, 'name' => $node->name, 'reason' => $raw ? 'Verantwortliche abwesend oder befangen, keine Vertretung' : 'niemand verantwortlich'];
            $node = $node->parent_entity_id ? OrganizationEntity::query()->find($node->parent_entity_id) : null;
            $distance++;
        }

        return ['entity_id' => $entityId, 'found' => false, 'node' => null, 'distance' => null, 'holders' => [], 'path' => $path];
    }

    /** Beziehungen und Rollen am Knoten, deren Fähigkeit die geforderte erfüllt – ohne Abwesenheit. */
    private function rawHolders(int $nodeId, string $capability, CarbonImmutable $at): array
    {
        $ok = Capability::satisfying($capability);
        $date = $at->toDateString();
        $holders = [];

        $types = DB::table('organization_entity_relation_types')->whereNotNull('capabilities')->where('is_active', true)
            ->get(['id', 'code', 'name', 'capabilities', 'responsibility_rank'])
            ->filter(fn ($t) => array_intersect((array) json_decode($t->capabilities ?? '[]', true), $ok) !== [])
            ->keyBy('id');
        if ($types->isNotEmpty()) {
            $rels = DB::table('organization_entity_relationships')
                ->where('to_entity_id', $nodeId)->whereIn('relation_type_id', $types->keys())->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
                ->get(['from_entity_id', 'relation_type_id']);
            foreach ($rels as $r) {
                $t = $types[$r->relation_type_id];
                $holders[] = ['subject' => (int) $r->from_entity_id, 'via' => 'relation', 'via_code' => $t->code, 'via_name' => $t->name, 'rank' => $t->responsibility_rank ?? self::NO_RANK];
            }
        }

        $roles = DB::table('organization_roles')->whereNotNull('capabilities')
            ->get(['id', 'slug', 'name', 'capabilities', 'responsibility_rank'])
            ->filter(fn ($r) => array_intersect((array) json_decode($r->capabilities ?? '[]', true), $ok) !== [])
            ->keyBy('id');
        if ($roles->isNotEmpty()) {
            $as = DB::table('organization_role_assignments')
                ->where('context_entity_id', $nodeId)->whereIn('role_id', $roles->keys())
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('organization_role_assignments', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
                ->get(['person_entity_id', 'role_id']);
            foreach ($as as $a) {
                $r = $roles[$a->role_id];
                $holders[] = ['subject' => (int) $a->person_entity_id, 'via' => 'role', 'via_code' => $r->slug, 'via_name' => $r->name, 'rank' => $r->responsibility_rank ?? self::NO_RANK];
            }
        }

        if (! $holders) {
            return [];
        }
        $entities = $this->actors(array_column($holders, 'subject'));
        $out = [];
        foreach ($holders as $h) {
            $e = $entities[$h['subject']] ?? null;
            if (! $e) {
                continue; // nur Personen und Agenten können verantwortlich sein
            }
            // dieselbe Person über mehrere Wege: der beste Rang zählt
            if (isset($out[$e['entity_id']]) && $out[$e['entity_id']]['rank'] <= $h['rank']) {
                continue;
            }
            $out[$e['entity_id']] = $e + ['via' => $h['via'], 'via_code' => $h['via_code'], 'via_name' => $h['via_name'], 'rank' => (int) $h['rank'],
                'node_entity_id' => $nodeId, 'deputy_for' => null];
        }

        return array_values($out);
    }

    /** @return array<int, array{entity_id:int,user_id:?int,name:string,kind:string}> */
    private function actors(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $rows = OrganizationEntity::query()->with('type:id,code')->whereIn('id', array_unique($ids))->where('is_active', true)->get(['id', 'name', 'linked_user_id', 'entity_type_id']);
        $out = [];
        foreach ($rows as $e) {
            $code = $e->type?->code;
            if (! in_array($code, ['person', 'agent'], true)) {
                continue;
            }
            $out[$e->id] = ['entity_id' => $e->id, 'user_id' => $e->linked_user_id ? (int) $e->linked_user_id : null, 'name' => $e->name, 'kind' => $code];
        }

        return $out;
    }

    private function deputiesFor(int $principalId, int $nodeId, CarbonImmutable $at, bool $whenAbsent): array
    {
        $scopes = array_merge([null], $this->ancestorsAndSelf($nodeId));
        $rows = OrganizationDeputy::query()->where('principal_entity_id', $principalId)->where('only_when_absent', $whenAbsent)
            ->validAt($at->toDateString())
            ->where(fn ($q) => $q->whereNull('scope_entity_id')->orWhereIn('scope_entity_id', array_filter($scopes)))
            ->orderByRaw('scope_entity_id IS NULL')->orderBy('id')->pluck('deputy_entity_id')->all();

        return array_values($this->actors($rows));
    }

    private function ancestorsAndSelf(int $nodeId): array
    {
        $ids = [];
        $id = $nodeId;
        while ($id && ! in_array($id, $ids, true) && count($ids) < 50) {
            $ids[] = $id;
            $id = (int) OrganizationEntity::query()->whereKey($id)->value('parent_entity_id');
        }

        return $ids;
    }

    /** Abwesenheit: Personen über den Vertrag Verfügbarkeit (falls gebunden), Agenten über ihren Heartbeat. */
    private function absenceOf(int $entityId, string $kind, CarbonImmutable $at): ?array
    {
        $key = $entityId.'@'.$at->toDateString();
        if (array_key_exists($key, $this->absenceCache)) {
            return $this->absenceCache[$key];
        }
        $absence = null;
        if ($kind === 'agent') {
            $p = OrganizationAgentProfile::query()->where('organization_entity_id', $entityId)->first();
            $limit = (int) config('organization.responsibility.agent_offline_minutes', 120);
            if ($p && (! $p->active || ! $p->last_heartbeat_at || $p->last_heartbeat_at->lt(now()->subMinutes($limit)))) {
                $absence = ['until' => null, 'kind' => 'offline'];
            }
        } elseif (app()->bound(AvailabilityProvider::class)) {
            try {
                $absence = app(AvailabilityProvider::class)->absence($entityId, $at);
            } catch (\Throwable) {
                $absence = null; // Verfügbarkeit unklar → als anwesend behandeln, nie blockieren
            }
        }

        return $this->absenceCache[$key] = $absence;
    }

    /** Der Mensch hinter einem Agenten: „Berichtet an“ vom Agenten zu einer Person. */
    private function supervisorOf(int $agentEntityId): ?array
    {
        $typeId = DB::table('organization_entity_relation_types')->where('code', 'reports_to')->value('id');
        if (! $typeId) {
            return null;
        }
        $to = DB::table('organization_entity_relationships')->where('from_entity_id', $agentEntityId)->where('relation_type_id', $typeId)
            ->whereNull('deleted_at')->orderByDesc('id')->value('to_entity_id');
        $e = $to ? ($this->actors([(int) $to])[(int) $to] ?? null) : null;

        return $e && $e['kind'] === 'person' ? $e : null;
    }

    private function viaText(array $h): string
    {
        return $h['via_name'];
    }
}

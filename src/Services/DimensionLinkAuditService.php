<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Read-only Integritäts-Audit für DimensionLinks.
 *
 * Prüft die plattformkritischen Invarianten, die heute nur in der App-Logik
 * (nicht in der DB) erzwungen werden. Dient als:
 *   - Phase-0-Bestandsaufnahme vor der Härtung
 *   - Verifikations-Instrument vor jeder riskanten Migration (Phase 4/5)
 *   - möglicher Health-Check (Command gibt bei Fund Exit-Code FAILURE zurück)
 *
 * Bewusst als wiederverwendbares Muster angelegt: ein `{Modul}AuditService`,
 * der eine Liste benannter Checks mit {label, status, count, samples} liefert.
 * Verändert nichts.
 */
class DimensionLinkAuditService
{
    /** Max. Beispielzeilen pro Check (nur für die Ausgabe, nicht für den Count). */
    private const SAMPLE_LIMIT = 15;

    /**
     * Führt alle Checks aus.
     *
     * @return array<string, array{label:string, status:string, count:int, samples:array<int,array<string,mixed>>}>
     */
    public function run(): array
    {
        return [
            'entity_values_without_source' => $this->entityValuesWithoutSource(),
            'orphan_entity_references'     => $this->orphanEntityReferences(),
            'percentage_overflow'          => $this->percentageOverflow(),
            'multiple_primary'             => $this->multiplePrimary(),
            'links_to_softdeleted_values'  => $this->linksToSoftDeletedValues(),
            'unknown_linkable_types'       => $this->unknownLinkableTypes(),
        ];
    }

    /**
     * Gruppierungs-Spalten für Invarianten pro Linkable. perspective_id nur, wenn
     * die Spalte in der (ggf. gedrifteten) Produktiv-Tabelle existiert.
     *
     * @return array<int,string>
     */
    private function groupColumns(): array
    {
        $cols = ['dimension_definition_id', 'linkable_type', 'linkable_id'];
        if (Schema::hasColumn('organization_dimension_links', 'perspective_id')) {
            $cols[] = 'perspective_id';
        }
        return $cols;
    }

    /**
     * Entity-basierte Dimension-Values ohne gültige metadata.source_entity_id.
     * Diese Werte lassen sich nicht auf eine Entity auflösen.
     */
    private function entityValuesWithoutSource(): array
    {
        $entityDefIds = OrganizationDimensionDefinition::where('value_source', 'entity')->pluck('id');

        $rows = OrganizationDimensionValue::whereIn('dimension_definition_id', $entityDefIds)
            ->whereNull('metadata->source_entity_id')
            ->get(['id', 'dimension_definition_id', 'code', 'name']);

        return $this->result(
            'Entity-Values ohne metadata.source_entity_id',
            $rows->count(),
            $rows->take(self::SAMPLE_LIMIT)->map(fn ($v) => [
                'value_id'   => $v->id,
                'def_id'     => $v->dimension_definition_id,
                'code'       => $v->code,
                'name'       => $v->name,
            ])->all()
        );
    }

    /**
     * Entity-basierte Values, deren source_entity_id auf eine nicht (mehr)
     * existente Entity zeigt (verwaiste Referenz).
     */
    private function orphanEntityReferences(): array
    {
        $entityDefIds = OrganizationDimensionDefinition::where('value_source', 'entity')->pluck('id');

        $values = OrganizationDimensionValue::whereIn('dimension_definition_id', $entityDefIds)
            ->whereNotNull('metadata->source_entity_id')
            ->get(['id', 'dimension_definition_id', 'code', 'name', 'metadata']);

        $referenced = $values
            ->map(fn ($v) => (int) ($v->metadata['source_entity_id'] ?? 0))
            ->filter()
            ->unique();

        $existing = OrganizationEntity::whereIn('id', $referenced)->pluck('id')->all();

        $orphans = $values->filter(
            fn ($v) => ! in_array((int) ($v->metadata['source_entity_id'] ?? 0), $existing, true)
        )->values();

        return $this->result(
            'Entity-Values mit verwaister source_entity_id (Entity existiert nicht)',
            $orphans->count(),
            $orphans->take(self::SAMPLE_LIMIT)->map(fn ($v) => [
                'value_id'         => $v->id,
                'def_id'           => $v->dimension_definition_id,
                'name'             => $v->name,
                'source_entity_id' => $v->metadata['source_entity_id'] ?? null,
            ])->all()
        );
    }

    /**
     * Mode=multi_percent: Link-Gruppen, deren Prozent-Summe > 100 ist.
     * Gruppe = (Definition, Linkable, Perspektive).
     */
    private function percentageOverflow(): array
    {
        if (! Schema::hasColumn('organization_dimension_links', 'percentage')) {
            return $this->skipped('Prozent-Summe > 100 (mode=multi_percent)', 'Spalte percentage fehlt (Schema-Drift)');
        }

        $pctDefIds = OrganizationDimensionDefinition::where('mode', 'multi_percent')->pluck('id');

        if ($pctDefIds->isEmpty()) {
            return $this->result('Prozent-Summe > 100 (mode=multi_percent)', 0, []);
        }

        $groupCols = $this->groupColumns();

        $rows = OrganizationDimensionLink::query()
            ->whereIn('dimension_definition_id', $pctDefIds)
            ->selectRaw(implode(', ', $groupCols) . ', SUM(percentage) as pct_sum, COUNT(*) as links')
            ->groupBy($groupCols)
            ->havingRaw('SUM(percentage) > 100')
            ->get();

        return $this->result(
            'Prozent-Summe > 100 (mode=multi_percent)',
            $rows->count(),
            $rows->take(self::SAMPLE_LIMIT)->map(fn ($r) => [
                'def_id'        => $r->dimension_definition_id,
                'linkable'      => $r->linkable_type . '#' . $r->linkable_id,
                'perspective'   => $r->perspective_id ?? null,
                'pct_sum'       => $r->pct_sum,
                'links'         => $r->links,
            ])->all()
        );
    }

    /**
     * Link-Gruppen mit mehr als einem is_primary=true.
     * Gruppe = (Definition, Linkable, Perspektive).
     */
    private function multiplePrimary(): array
    {
        if (! Schema::hasColumn('organization_dimension_links', 'is_primary')) {
            return $this->skipped('Mehr als ein is_primary pro Gruppe', 'Spalte is_primary fehlt (Schema-Drift)');
        }

        $groupCols = $this->groupColumns();

        $rows = OrganizationDimensionLink::query()
            ->selectRaw(implode(', ', $groupCols) . ', SUM(is_primary) as primaries, COUNT(*) as links')
            ->groupBy($groupCols)
            ->havingRaw('SUM(is_primary) > 1')
            ->get();

        return $this->result(
            'Mehr als ein is_primary pro Gruppe',
            $rows->count(),
            $rows->take(self::SAMPLE_LIMIT)->map(fn ($r) => [
                'def_id'      => $r->dimension_definition_id,
                'linkable'    => $r->linkable_type . '#' . $r->linkable_id,
                'perspective' => $r->perspective_id ?? null,
                'primaries'   => $r->primaries,
                'links'       => $r->links,
            ])->all()
        );
    }

    /**
     * Links, die auf einen soft-gelöschten Dimension-Value zeigen.
     * Join direkt auf die Tabelle, damit der SoftDelete-Scope die Zeilen nicht ausblendet.
     */
    private function linksToSoftDeletedValues(): array
    {
        $rows = OrganizationDimensionLink::query()
            ->join('organization_dimension_values as v', 'v.id', '=', 'organization_dimension_links.dimension_value_id')
            ->whereNotNull('v.deleted_at')
            ->select(
                'organization_dimension_links.id as link_id',
                'organization_dimension_links.dimension_value_id',
                'organization_dimension_links.linkable_type',
                'organization_dimension_links.linkable_id',
                'v.name as value_name'
            )
            ->get();

        return $this->result(
            'Links auf soft-gelöschte Values',
            $rows->count(),
            $rows->take(self::SAMPLE_LIMIT)->map(fn ($r) => [
                'link_id'   => $r->link_id,
                'value_id'  => $r->dimension_value_id,
                'value'     => $r->value_name,
                'linkable'  => $r->linkable_type . '#' . $r->linkable_id,
            ])->all()
        );
    }

    /**
     * linkable_type-Werte, die weder ein registrierter Morph-Alias noch eine
     * existierende Klasse sind — können per morphTo() nicht aufgelöst werden.
     */
    private function unknownLinkableTypes(): array
    {
        $known = array_keys(Relation::morphMap());

        // Aufschlüsselung je (linkable_type, Dimension) — zeigt, an welchen
        // Dimensionen unauflösbare Typen hängen (Untersuchung vor Morph-Guard).
        $counts = OrganizationDimensionLink::query()
            ->selectRaw('linkable_type, dimension_definition_id, COUNT(*) as links')
            ->groupBy('linkable_type', 'dimension_definition_id')
            ->get();

        $unknown = $counts->filter(
            fn ($r) => ! in_array($r->linkable_type, $known, true) && ! class_exists($r->linkable_type)
        )->values();

        $defs = OrganizationDimensionDefinition::whereIn('id', $unknown->pluck('dimension_definition_id')->unique())
            ->get()
            ->keyBy('id');

        return $this->result(
            'Unbekannte linkable_type (kein Morph-Alias, keine Klasse)',
            (int) $unknown->sum('links'),
            $unknown->sortByDesc('links')->take(self::SAMPLE_LIMIT)->map(fn ($r) => [
                'linkable_type' => $r->linkable_type,
                'dimension'     => $defs->get($r->dimension_definition_id)?->key ?? ('def#' . $r->dimension_definition_id),
                'links'         => $r->links,
            ])->values()->all()
        );
    }

    /** @param array<int,array<string,mixed>> $samples */
    private function result(string $label, int $count, array $samples): array
    {
        return [
            'label'   => $label,
            'status'  => $count > 0 ? 'warn' : 'ok',
            'count'   => $count,
            'samples' => $samples,
        ];
    }

    /** Check konnte wegen Schema-Drift nicht laufen — kein Fund, aber sichtbar. */
    private function skipped(string $label, string $reason): array
    {
        return [
            'label'   => $label,
            'status'  => 'skipped',
            'count'   => 0,
            'samples' => [],
            'note'    => $reason,
        ];
    }
}

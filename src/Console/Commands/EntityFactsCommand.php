<?php

namespace Platform\Organization\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\Story\EntityFactsService;

/**
 * Zeigt die Fakten-Schicht der Knoten-Erzählung für eine Entity — zum Prüfen, bevor daraus Sätze werden.
 */
class EntityFactsCommand extends Command
{
    protected $signature = 'organization:entity-facts
        {entity : Entity-ID}
        {--period=week : week | month | quarter}
        {--as-of= : Stichtag (YYYY-MM-DD), Default heute}
        {--json : Rohdaten als JSON}';

    protected $description = 'Fakten-Schicht der Knoten-Erzählung: Bewegung je Dimension, Energie aus der Quelle, Kinder.';

    public function handle(EntityFactsService $service): int
    {
        $entity = OrganizationEntity::find((int) $this->argument('entity'));
        if (! $entity) {
            $this->error('Entity nicht gefunden.');
            return self::FAILURE;
        }
        $period = (string) $this->option('period');
        if (! in_array($period, EntityFactsService::PERIODS, true)) {
            $this->error('--period muss week, month oder quarter sein.');
            return self::FAILURE;
        }
        $asOf = $this->option('as-of') ? Carbon::parse($this->option('as-of')) : null;

        $facts = $service->forEntity($entity, $period, $asOf);

        if ($this->option('json')) {
            $this->line(json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $p = $facts['period'];
        $this->info("{$facts['entity']['name']} · {$p['label']} ({$p['from']} – {$p['to']})");
        $this->line("Snapshots: {$facts['snapshots']['current']} gegen " . ($facts['snapshots']['base'] ?? 'keinen'));

        $e = $facts['energy'];
        $this->line(sprintf('Energie: %.1f h (Agenten %.1f h), Vorperiode %.1f h%s',
            $e['minutes'] / 60, $e['agent_minutes'] / 60, $e['previous_minutes'] / 60,
            $e['delta_pct'] !== null ? " ({$e['delta_pct']} %)" : ''));
        foreach ($e['people'] as $x) {
            $this->line(sprintf('  %s%s %.1f h', $x['name'] ?? '?', $x['agent'] ? ' (Agent)' : '', $x['minutes'] / 60));
        }
        foreach ($e['contexts'] as $x) {
            $this->line(sprintf('  · %s %.1f h', $x['label'] ?? '?', $x['minutes'] / 60));
        }

        foreach (['good' => 'Gut', 'bad' => 'Schlecht', 'neutral' => 'Neutral'] as $k => $label) {
            foreach ($facts['movers'][$k] as $m) {
                $this->line(sprintf('%s: %s %s%s (%s %s)', $label, $m['label'], $m['delta'] > 0 ? '+' : '', $m['delta'],
                    $m['flow'] !== null ? 'im Zeitraum' : 'jetzt', $m['flow'] ?? $m['current']));
            }
        }

        $rows = [];
        foreach ($facts['dimensions'] as $dim) {
            foreach ($dim['metrics'] as $m) {
                $rows[] = [$dim['label'], ($m['primary'] ? '★ ' : '') . $m['label'], $m['basis'], $m['current'], $m['previous'] ?? '–', $m['delta'] ?? '–', $m['sentiment']];
            }
        }
        $this->table(['Dimension', 'Metrik', 'Basis', 'Stand', 'Vorher', 'Delta', 'Lesart'], $rows);

        if ($facts['excluded']) {
            $this->table(['Ausgeschlossen', 'Grund', 'Stand', 'Vorher'], array_map(
                fn ($x) => [$x['label'], $x['reason'], $x['current'], $x['previous'] ?? '–'], $facts['excluded']));
        }

        $s = $facts['stillness'];
        $this->line("Signale offen: {$facts['signals']['open']} (kritisch {$facts['signals']['critical']}) · letzte Buchung: " . ($s['last_booking'] ?? '–')
            . ($s['days_since_last_booking'] !== null ? " (vor {$s['days_since_last_booking']} Tagen)" : '') . ($facts['quiet'] ? ' · ruhig' : ''));

        if (! empty($facts['children'])) {
            $this->table(['Kind', 'h', 'gut', 'schlecht', 'Top-Bewegung', 'Zustand'], array_map(fn ($c) => [
                $c['name'], round($c['minutes'] / 60, 1), $c['good'], $c['bad'],
                $c['top'] ? $c['top']['label'] . ' ' . ($c['top']['delta'] > 0 ? '+' : '') . $c['top']['delta'] : '–',
                $c['mute'] ? 'stumm' : ($c['quiet'] ? 'ruhig' : 'bewegt'),
            ], $facts['children']));
        }

        return self::SUCCESS;
    }
}

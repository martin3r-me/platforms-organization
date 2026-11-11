<?php

namespace Platform\Organization\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityStory;
use Platform\Organization\Services\Story\EntityFactsService;
use Platform\Organization\Services\Story\EntityStoryWriter;

/**
 * Täglicher Lauf der Knoten-Erzählung: für jeden aktiven Knoten Fakten rechnen, Kinder-Kurzfassungen
 * aus denselben Fakten anhängen (einmal rechnen, von unten nach oben lesen), Satz schreiben, speichern.
 *
 * period=auto: Woche jeden Tag; Monat und Quartal montags oder wenn die letzte älter als 7 Tage ist.
 * KI-Sätze nur für die bewegtesten Knoten (--max-llm), alle anderen bekommen den festen Satz.
 */
class EntityStoriesCommand extends Command
{
    protected $signature = 'organization:entity-stories
        {--period=auto : auto | week | month | quarter}
        {--team= : Nur Knoten dieses Teams}
        {--entity= : Nur dieser Knoten (Kinder werden mitgerechnet)}
        {--as-of= : Stichtag (YYYY-MM-DD), Default heute}
        {--max-llm=150 : Höchstens so viele KI-Sätze je Zeitraum}
        {--no-llm : Nur feste Sätze}
        {--dry-run : Nichts speichern, Sätze ausgeben}';

    protected $description = 'Rechnet und speichert die Lage-Erzählung je Knoten (Fakten + Satz).';

    public function handle(EntityFactsService $facts, EntityStoryWriter $writer): int
    {
        $asOf = $this->option('as-of') ? Carbon::parse($this->option('as-of')) : now();
        $periods = $this->periods((string) $this->option('period'), $asOf);
        if ($periods === null) {
            $this->error('--period muss auto, week, month oder quarter sein.');
            return self::FAILURE;
        }

        $entities = $this->entities();
        if ($entities->isEmpty()) {
            $this->warn('Keine aktiven Knoten.');
            return self::SUCCESS;
        }

        foreach ($periods as $period) {
            $this->runPeriod($period, $entities, $asOf, $facts, $writer);
        }

        return self::SUCCESS;
    }

    protected function runPeriod(string $period, $entities, Carbon $asOf, EntityFactsService $facts, EntityStoryWriter $writer): void
    {
        $started = microtime(true);
        $map = [];
        $failed = 0;
        foreach ($entities as $entity) {
            try {
                $map[$entity->id] = $facts->forEntity($entity, $period, $asOf, false);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('entity-stories: Fakten fehlgeschlagen', ['entity' => $entity->id, 'period' => $period, 'error' => $e->getMessage()]);
            }
        }

        // Kinder aus denselben Fakten anhängen
        $byParent = $entities->groupBy('parent_entity_id');
        foreach ($map as $id => &$f) {
            $f['children'] = collect($byParent->get($id, []))
                ->filter(fn ($c) => isset($map[$c->id]))
                ->map(fn ($c) => $facts->summarize($map[$c->id]))
                ->sortByDesc('weight')->values()->all();
        }
        unset($f);

        // KI-Budget: die bewegtesten Knoten zuerst
        $budget = $this->option('no-llm') ? 0 : max(0, (int) $this->option('max-llm'));
        $llmIds = collect($map)->reject(fn ($f) => $f['quiet'])
            ->sortByDesc(fn ($f) => $facts->summarize($f)['weight'] + 30 * count($f['children']))
            ->keys()->take($budget)->flip();

        $counts = ['llm' => 0, 'template' => 0];
        foreach ($map as $id => $f) {
            $entity = $entities->firstWhere('id', $id);
            $story = $writer->write($f, $llmIds->has($id));
            $counts[$story['source']]++;

            if ($this->option('dry-run')) {
                $this->line("[{$period}] {$entity->name} ({$story['source']}): {$story['story']}");
                continue;
            }

            OrganizationEntityStory::updateOrCreate(
                ['entity_id' => $id, 'period' => $period, 'as_of' => $asOf->toDateString()],
                ['team_id' => $entity->team_id, 'facts' => $f, 'story' => $story['story'], 'source' => $story['source'],
                    'model' => $story['model'], 'generated_at' => now()],
            );
        }

        $this->info(sprintf('%s: %d Knoten · %d KI-Sätze · %d feste Sätze · %d Fehler · %.1f s',
            $period, count($map), $counts['llm'], $counts['template'], $failed, microtime(true) - $started));
    }

    /** @return string[]|null */
    protected function periods(string $option, Carbon $asOf): ?array
    {
        if (in_array($option, EntityFactsService::PERIODS, true)) {
            return [$option];
        }
        if ($option !== 'auto') {
            return null;
        }

        $periods = ['week'];
        foreach (['month', 'quarter'] as $p) {
            $last = OrganizationEntityStory::where('period', $p)->max('as_of');
            if ($asOf->isMonday() || ! $last || Carbon::parse($last)->lt($asOf->copy()->subDays(7))) {
                $periods[] = $p;
            }
        }

        return $periods;
    }

    protected function entities()
    {
        $query = OrganizationEntity::query()->where('is_active', true)->with('type');

        if ($this->option('entity')) {
            $root = (int) $this->option('entity');
            $query->where(fn ($q) => $q->where('id', $root)->orWhere('parent_entity_id', $root));
        } elseif ($this->option('team')) {
            $query->where('team_id', (int) $this->option('team'));
        }

        return $query->get(['id', 'name', 'team_id', 'parent_entity_id', 'entity_type_id']);
    }
}

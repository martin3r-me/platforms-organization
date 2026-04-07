<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Normalisiert organization_entity_links.linkable_type auf den kanonischen Morph-Alias.
 * Behebt Altlasten wie FQCN-Referenzen und Legacy-Aliase (planner_project → project).
 */
class NormalizeEntityLinkTypesCommand extends Command
{
    protected $signature = 'organization:normalize-entity-link-types {--dry-run}';

    protected $description = 'Normalize linkable_type values in organization_entity_links to canonical morph aliases';

    protected array $legacyAliasMap = [
        'planner_project' => 'project',
        'planner_task'    => 'task',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $morphMap = Relation::morphMap() ?: [];
        $reverse = array_flip($morphMap);

        $distinct = DB::table('organization_entity_links')
            ->select('linkable_type')
            ->distinct()
            ->pluck('linkable_type');

        $updates = [];
        foreach ($distinct as $type) {
            $normalized = $this->normalize($type, $morphMap, $reverse);
            if ($normalized !== $type) {
                $count = DB::table('organization_entity_links')
                    ->where('linkable_type', $type)
                    ->count();
                $updates[] = [$type, $normalized, $count];
            }
        }

        if (empty($updates)) {
            $this->info('All linkable_type values already normalized.');
            return self::SUCCESS;
        }

        $this->table(['From', 'To', 'Rows'], $updates);

        if ($dry) {
            $this->warn('Dry run — no changes made.');
            return self::SUCCESS;
        }

        foreach ($updates as [$from, $to, $count]) {
            DB::table('organization_entity_links')
                ->where('linkable_type', $from)
                ->update(['linkable_type' => $to]);
            $this->info("Updated {$count} rows: {$from} → {$to}");
        }

        return self::SUCCESS;
    }

    protected function normalize(string $type, array $morphMap, array $reverse): string
    {
        $type = ltrim($type, '\\');
        if ($type === '') return $type;

        if (isset($this->legacyAliasMap[$type])) {
            return $this->legacyAliasMap[$type];
        }

        if (array_key_exists($type, $morphMap)) {
            return $type;
        }
        if (isset($reverse[$type])) {
            return $reverse[$type];
        }
        if (class_exists($type)) {
            $snake = Str::snake(class_basename($type));
            return $this->legacyAliasMap[$snake] ?? $snake;
        }
        return $type;
    }
}

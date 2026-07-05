<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationForecast;
use Platform\Organization\Models\OrganizationStrategicDocument;

/**
 * Kombinierte Sicht auf das strategische Zukunftsbild einer Carrier-Entity.
 *
 * Ersetzt 6+ Einzel-Calls (strategic_documents, forecasts, focus_areas,
 * vision_images, obstacles, milestones). Struktur identisch zur UI-Strategie-Tab.
 */
class GetEntityStrategyTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.strategy.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entities/{id}/strategy - Kombinierte Sicht: Mission/Vision + Forecasts + Fokusraeume + Zielbilder + Hindernisse + Meilensteine + Transformation-Map. Nur fuer Carrier-Entities. Argumente: entity_id (pflicht), include_content (default true, laedt Markdown-Content von Mission/Vision/Forecast-Version).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'PFLICHT. ID der Carrier-Entity, deren Zukunftsbild geladen wird.',
                ],
                'include_content' => [
                    'type' => 'boolean',
                    'description' => 'Optional (default true): lade Markdown-Content von Mission, Vision und aktueller Forecast-Version. Auf false setzen fuer kompakte Struktur-Sicht ohne Text.',
                ],
            ],
            'required' => ['entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }
            $includeContent = array_key_exists('include_content', $arguments)
                ? (bool) $arguments['include_content']
                : true;

            $entity = OrganizationEntity::query()
                ->with('type:id,code,name,vsm_class')
                ->find($entityId);

            if (! $entity) {
                return ToolResult::error('NOT_FOUND', "Entity #{$entityId} nicht gefunden.");
            }

            $isCarrier = $entity->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER;
            if (! $isCarrier) {
                return ToolResult::success([
                    'entity' => [
                        'id'         => $entity->id,
                        'name'       => $entity->name,
                        'code'       => $entity->code,
                        'type_code'  => $entity->type?->code,
                        'type_name'  => $entity->type?->name,
                        'vsm_class'  => $entity->type?->vsm_class,
                    ],
                    'is_carrier' => false,
                    'message'    => 'Nur Carrier-Entities tragen ein strategisches Zukunftsbild.',
                    'mission'    => null,
                    'vision'     => null,
                    'forecasts'  => [],
                    'totals'     => $this->emptyTotals(),
                ]);
            }

            $mission = $this->loadDoc($entityId, 'mission', $includeContent);
            $vision  = $this->loadDoc($entityId, 'vision', $includeContent);

            $forecasts = OrganizationForecast::query()
                ->where('entity_id', $entityId)
                ->whereNull('deleted_at')
                ->with([
                    'currentVersion',
                    'focusAreas' => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                    'focusAreas.visionImages' => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                    'focusAreas.obstacles' => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                    'focusAreas.milestones' => fn ($q) => $q->whereNull('deleted_at')
                        ->orderBy('target_year')->orderBy('target_quarter')->orderBy('order'),
                ])
                ->orderBy('target_date')
                ->get();

            $totals = $this->emptyTotals();
            $forecastData = $forecasts->map(function ($f) use ($includeContent, &$totals) {
                $years = [];
                $grid = [];
                $noYear = [];

                $focusAreas = $f->focusAreas->map(function ($fa) use (&$totals, &$years, &$grid, &$noYear) {
                    $milestones = $fa->milestones->map(function ($m) {
                        return [
                            'id'             => $m->id,
                            'title'          => $m->title,
                            'description'    => $m->description,
                            'central_question' => $m->central_question,
                            'target_year'    => $m->target_year !== null ? (int) $m->target_year : null,
                            'target_quarter' => $m->target_quarter !== null ? (int) $m->target_quarter : null,
                            'target_date'    => $m->target_date?->toDateString(),
                            'order'          => (int) $m->order,
                        ];
                    })->values()->toArray();

                    foreach ($milestones as $m) {
                        if ($m['target_year']) {
                            $years[$m['target_year']] = true;
                            $grid[$fa->id][$m['target_year']][] = $m;
                        } else {
                            $noYear[$fa->id][] = $m;
                        }
                    }

                    $visionImages = $fa->visionImages->map(fn ($vi) => [
                        'id'          => $vi->id,
                        'title'       => $vi->title,
                        'description' => $vi->description,
                        'order'       => (int) $vi->order,
                    ])->values()->toArray();

                    $obstacles = $fa->obstacles->map(fn ($ob) => [
                        'id'          => $ob->id,
                        'title'       => $ob->title,
                        'description' => $ob->description,
                        'order'       => (int) $ob->order,
                    ])->values()->toArray();

                    $totals['focus_areas']   += 1;
                    $totals['vision_images'] += count($visionImages);
                    $totals['obstacles']     += count($obstacles);
                    $totals['milestones']    += count($milestones);

                    return [
                        'id'                        => $fa->id,
                        'title'                     => $fa->title,
                        'description'               => $fa->description,
                        'order'                     => (int) $fa->order,
                        'central_question_vision_images' => $fa->central_question_vision_images,
                        'central_question_obstacles'     => $fa->central_question_obstacles,
                        'central_question_milestones'    => $fa->central_question_milestones,
                        'vision_images' => $visionImages,
                        'obstacles'     => $obstacles,
                        'milestones'    => $milestones,
                    ];
                })->values()->toArray();

                ksort($years);
                $totals['forecasts'] += 1;

                return [
                    'id'                 => $f->id,
                    'uuid'               => $f->uuid,
                    'title'              => $f->title,
                    'target_date'        => $f->target_date?->toDateString(),
                    'current_version'    => $f->currentVersion?->version,
                    'current_version_id' => $f->currentVersion?->id,
                    'content'            => $includeContent ? ($f->currentVersion?->content ?? $f->content) : null,
                    'focus_areas'        => $focusAreas,
                    'transformation_map' => [
                        'years'   => array_keys($years),
                        'grid'    => $grid,
                        'no_year' => $noYear,
                    ],
                ];
            })->values()->toArray();

            return ToolResult::success([
                'entity' => [
                    'id'         => $entity->id,
                    'name'       => $entity->name,
                    'code'       => $entity->code,
                    'type_code'  => $entity->type?->code,
                    'type_name'  => $entity->type?->name,
                    'vsm_class'  => $entity->type?->vsm_class,
                ],
                'is_carrier' => true,
                'mission'    => $mission,
                'vision'     => $vision,
                'forecasts'  => $forecastData,
                'totals'     => $totals,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function loadDoc(int $entityId, string $type, bool $includeContent): ?array
    {
        $doc = OrganizationStrategicDocument::query()
            ->where('entity_id', $entityId)
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first();

        if (! $doc) {
            return null;
        }

        return [
            'id'         => $doc->id,
            'uuid'       => $doc->uuid,
            'title'      => $doc->title,
            'version'    => $doc->version,
            'valid_from' => $doc->valid_from?->toDateString(),
            'content'    => $includeContent ? $doc->content : null,
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'forecasts'     => 0,
            'focus_areas'   => 0,
            'vision_images' => 0,
            'obstacles'     => 0,
            'milestones'    => 0,
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'strategy', 'aggregate', 'carrier'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}

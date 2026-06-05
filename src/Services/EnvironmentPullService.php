<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationMemoryEntry;

class EnvironmentPullService
{
    public function pullSource(OrganizationEnvironmentSource $source): ?OrganizationEnvironmentSnapshot
    {
        if ($source->source_type === 'kpi') {
            return $this->pullKpiSource($source);
        }

        $items = match ($source->source_type) {
            'rss' => $this->fetchRss($source),
            default => [],
        };

        if (empty($items)) {
            $source->update(['last_pulled_at' => now()]);

            return null;
        }

        $extracted = $this->extractWithLlm($source, $items);

        $snapshot = OrganizationEnvironmentSnapshot::create([
            'team_id' => $source->team_id,
            'source_id' => $source->id,
            'snapshot_date' => now()->toDateString(),
            'metrics' => [
                'new_items_count' => count($items),
                'sentiment_score' => $extracted['sentiment_score'] ?? null,
                'relevance_score' => $extracted['relevance_score'] ?? null,
                'topics' => $extracted['topics'] ?? [],
                'org_relevance_reasoning' => $extracted['org_relevance_reasoning'] ?? null,
            ],
            'summary' => $extracted['summary'] ?? '',
            'raw_items' => array_slice($items, 0, 20),
        ]);

        $source->update(['last_pulled_at' => now()]);

        return $snapshot;
    }

    protected function pullKpiSource(OrganizationEnvironmentSource $source): ?OrganizationEnvironmentSnapshot
    {
        if (! class_exists(\Platform\Datawarehouse\Models\DatawarehouseKpi::class)) {
            Log::info('EnvironmentPullService: Datawarehouse-Modul nicht verfügbar, KPI-Pull übersprungen.', [
                'source_id' => $source->id,
            ]);
            $source->update(['last_pulled_at' => now()]);

            return null;
        }

        $config = $source->config ?? [];
        $kpiId = $config['kpi_id'] ?? null;

        if (! $kpiId) {
            Log::warning('EnvironmentPullService: KPI-Source ohne kpi_id.', ['source_id' => $source->id]);
            $source->update(['last_pulled_at' => now()]);

            return null;
        }

        $kpi = \Platform\Datawarehouse\Models\DatawarehouseKpi::find($kpiId);
        if (! $kpi) {
            Log::warning('EnvironmentPullService: KPI nicht gefunden.', [
                'source_id' => $source->id,
                'kpi_id' => $kpiId,
            ]);
            $source->update(['last_pulled_at' => now()]);

            return null;
        }

        // Refresh KPI cache if stale
        if (! $kpi->isCacheValid()) {
            try {
                $builder = new \Platform\Datawarehouse\Services\KpiQueryBuilder();
                $builder->executeAndCache($kpi, 'environment_pull');
                $kpi->refresh();
            } catch (\Throwable $e) {
                Log::warning('EnvironmentPullService: KPI-Berechnung fehlgeschlagen.', [
                    'source_id' => $source->id,
                    'kpi_id' => $kpiId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $value = $kpi->cached_value !== null ? (float) $kpi->cached_value : null;
        $comparisonValue = $kpi->cached_comparison_value !== null ? (float) $kpi->cached_comparison_value : null;

        // Calculate change percentage
        $changePct = null;
        if ($value !== null && $comparisonValue !== null && $comparisonValue != 0) {
            $changePct = round((($value - $comparisonValue) / abs($comparisonValue)) * 100, 2);
        }

        $trend = $kpi->trendDirection();
        $trendValue = $kpi->trendValue();
        $displayRange = $kpi->displayRangeLabel();

        $metrics = [
            'kpi_id' => $kpi->id,
            'kpi_name' => $kpi->name,
            'value' => $value,
            'comparison_value' => $comparisonValue,
            'change_pct' => $changePct,
            'trend' => $trend,
            'trend_value' => $trendValue,
            'display_range' => $displayRange,
            'unit' => $kpi->unit,
            'format' => $kpi->format,
        ];

        // Build summary
        $formattedValue = $this->formatKpiValue($value, $kpi->decimals ?? 0, $kpi->unit);
        $trendLabel = match ($trend) {
            'up' => 'gestiegen',
            'down' => 'gesunken',
            default => 'unverändert',
        };

        $summaryTemplate = $config['summary_template'] ?? null;
        if ($summaryTemplate) {
            $summary = str_replace(
                ['{kpi_name}', '{value}', '{trend_value}', '{trend_label}', '{display_range}', '{unit}'],
                [$kpi->name, $formattedValue, $trendValue ?? '-', $trendLabel, $displayRange ?? '-', $kpi->unit ?? ''],
                $summaryTemplate
            );
        } else {
            $summary = "{$kpi->name}: {$formattedValue}";
            if ($trendValue) {
                $summary .= " ({$trendValue}, {$trendLabel})";
            }
            if ($displayRange) {
                $summary .= " [{$displayRange}]";
            }
        }

        $snapshot = OrganizationEnvironmentSnapshot::create([
            'team_id' => $source->team_id,
            'source_id' => $source->id,
            'snapshot_date' => now()->toDateString(),
            'metrics' => $metrics,
            'summary' => $summary,
            'raw_items' => [],
        ]);

        $source->update(['last_pulled_at' => now()]);

        return $snapshot;
    }

    protected function formatKpiValue(?float $value, int $decimals, ?string $unit): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = number_format($value, $decimals, ',', '.');

        if ($unit) {
            $formatted .= ' ' . $unit;
        }

        return $formatted;
    }

    public function fetchRss(OrganizationEnvironmentSource $source): array
    {
        $url = $source->config['url'] ?? null;
        if (! $url) {
            return [];
        }

        try {
            $response = Http::timeout(30)->get($url);
            if (! $response->successful()) {
                Log::warning('EnvironmentPullService: RSS fetch failed', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (! $xml) {
                Log::warning('EnvironmentPullService: Invalid XML', ['source_id' => $source->id]);

                return [];
            }

            $items = [];

            // RSS 2.0 (<channel><item>)
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $items[] = $this->parseRssItem($item);
                }
            }
            // Atom (<feed><entry>)
            elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $items[] = $this->parseAtomEntry($entry);
                }
            }

            // Filter: only items newer than last_pulled_at
            if ($source->last_pulled_at) {
                $cutoff = $source->last_pulled_at->timestamp;
                $items = array_filter($items, function ($item) use ($cutoff) {
                    if (! $item['pubDate']) {
                        return true;
                    }
                    $ts = strtotime($item['pubDate']);

                    return $ts !== false && $ts > $cutoff;
                });
                $items = array_values($items);
            }

            return array_slice($items, 0, 50);
        } catch (\Throwable $e) {
            Log::warning('EnvironmentPullService: RSS fetch exception', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function parseRssItem(\SimpleXMLElement $item): array
    {
        return [
            'title' => (string) ($item->title ?? ''),
            'link' => (string) ($item->link ?? ''),
            'pubDate' => (string) ($item->pubDate ?? ''),
            'description' => mb_substr(strip_tags((string) ($item->description ?? '')), 0, 500),
        ];
    }

    protected function parseAtomEntry(\SimpleXMLElement $entry): array
    {
        $link = '';
        if (isset($entry->link)) {
            $link = (string) ($entry->link['href'] ?? $entry->link);
        }

        return [
            'title' => (string) ($entry->title ?? ''),
            'link' => $link,
            'pubDate' => (string) ($entry->published ?? $entry->updated ?? ''),
            'description' => mb_substr(strip_tags((string) ($entry->summary ?? $entry->content ?? '')), 0, 500),
        ];
    }

    public function buildExtractionContext(OrganizationEnvironmentSource $source): string
    {
        $parts = [];

        // 1. Leitbild (Perspektive) via SemanticLayerResolver
        try {
            $team = Team::find($source->team_id);
            if ($team) {
                $resolver = resolve(\Platform\Core\SemanticLayer\Services\SemanticLayerResolver::class);
                $resolved = $resolver->resolveFor($team, 'environment');
                if (! $resolved->isEmpty()) {
                    $parts[] = "## Organisation\n" . $resolved->rendered_block;
                }
            }
        } catch (\Throwable) {
            // SemanticLayer module may not be available
        }

        // 2. Zukunftsbild (Focus Area Titles)
        try {
            $forecast = \Platform\Okr\Models\Forecast::where('team_id', $source->team_id)
                ->latest()
                ->first();
            if ($forecast) {
                $focusTitles = $forecast->focusAreas()->pluck('title')->implode(', ');
                if ($focusTitles) {
                    $parts[] = "## Strategische Fokusfelder\n{$focusTitles}";
                }
            }
        } catch (\Throwable) {
            // OKR module may not be available
        }

        // 3. Gelerntes Feedback aus source_relevance Memory
        $relevanceMemory = OrganizationMemoryEntry::forTeam($source->team_id)
            ->ofType('source_relevance')
            ->active()
            ->valid()
            ->where('content', 'like', '%' . $source->name . '%')
            ->orderByDesc('confidence')
            ->first();

        if ($relevanceMemory && ! empty($relevanceMemory->structured_data)) {
            $data = $relevanceMemory->structured_data;
            $feedbackParts = [];
            if (! empty($data['topics_useful'])) {
                $feedbackParts[] = 'Besonders relevante Themen: ' . implode(', ', $data['topics_useful']);
            }
            if (! empty($data['topics_noise'])) {
                $feedbackParts[] = 'Als irrelevant bekannte Themen: ' . implode(', ', $data['topics_noise']);
            }
            if (! empty($feedbackParts)) {
                $parts[] = "## Gelerntes Feedback\n" . implode("\n", $feedbackParts);
            }
        }

        return implode("\n\n", $parts);
    }

    public function extractWithLlm(OrganizationEnvironmentSource $source, array $items): array
    {
        $apiKey = config('ai.anthropic.api_key');
        if (! $apiKey) {
            return $this->fallbackExtraction($items);
        }

        $itemsText = collect($items)->map(function ($item, $i) {
            return ($i + 1) . ". {$item['title']}\n   {$item['description']}";
        })->implode("\n\n");

        $customPrompt = $source->config['extraction_prompt'] ?? '';
        $userMessage = "Kategorie: {$source->category}\n\n";
        if ($customPrompt) {
            $userMessage .= "Zusätzlicher Kontext: {$customPrompt}\n\n";
        }
        $userMessage .= "Feed-Items:\n\n{$itemsText}";

        $orgContext = $this->buildExtractionContext($source);

        $systemPrompt = "Du extrahierst strukturierte Informationen aus Nachrichten-Feeds für eine spezifische Organisation.";
        if ($orgContext) {
            $systemPrompt .= "\n\n{$orgContext}";
        }
        $systemPrompt .= <<<'PROMPT'


Bewerte relevance_score aus der Perspektive DIESER Organisation:
- 1.0 = direkt relevant für strategische Fokusfelder oder Kerngeschäft
- 0.7 = relevant für die Branche/Umfeld
- 0.3 = allgemein interessant, kein direkter Bezug
- 0.0 = irrelevant

Antworte mit validem JSON:
{"summary": "2-3 Sätze Zusammenfassung", "sentiment_score": <-1 bis 1>, "relevance_score": <0 bis 1>, "topics": ["topic1", "topic2"], "org_relevance_reasoning": "1 Satz warum relevant/irrelevant"}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 1024,
                'temperature' => 0,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('EnvironmentPullService: LLM API error', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);

                return $this->fallbackExtraction($items);
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            // Extract JSON from response
            $jsonMatch = [];
            if (preg_match('/\{.*\}/s', $text, $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if (is_array($parsed)) {
                    return [
                        'summary' => $parsed['summary'] ?? '',
                        'sentiment_score' => max(-1.0, min(1.0, (float) ($parsed['sentiment_score'] ?? 0))),
                        'relevance_score' => max(0.0, min(1.0, (float) ($parsed['relevance_score'] ?? 0.5))),
                        'topics' => array_slice((array) ($parsed['topics'] ?? []), 0, 10),
                        'org_relevance_reasoning' => $parsed['org_relevance_reasoning'] ?? null,
                    ];
                }
            }

            return $this->fallbackExtraction($items);
        } catch (\Throwable $e) {
            Log::warning('EnvironmentPullService: LLM extraction failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackExtraction($items);
        }
    }

    protected function fallbackExtraction(array $items): array
    {
        $titles = collect($items)->pluck('title')->filter()->take(5)->implode('; ');

        return [
            'summary' => "Feed enthält " . count($items) . " neue Items. Themen: {$titles}",
            'sentiment_score' => 0,
            'relevance_score' => 0.5,
            'topics' => collect($items)->pluck('title')->filter()->take(5)->values()->all(),
        ];
    }
}

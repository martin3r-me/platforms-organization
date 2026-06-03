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
    protected const WMO_CODES = [
        0 => 'Klar',
        1 => 'Überwiegend klar',
        2 => 'Teilweise bewölkt',
        3 => 'Bewölkt',
        45 => 'Nebel',
        48 => 'Nebel mit Reifbildung',
        51 => 'Leichter Nieselregen',
        53 => 'Mäßiger Nieselregen',
        55 => 'Starker Nieselregen',
        56 => 'Gefrierender Nieselregen',
        57 => 'Starker gefrierender Nieselregen',
        61 => 'Leichter Regen',
        63 => 'Mäßiger Regen',
        65 => 'Starker Regen',
        66 => 'Gefrierender Regen',
        67 => 'Starker gefrierender Regen',
        71 => 'Leichter Schneefall',
        73 => 'Mäßiger Schneefall',
        75 => 'Starker Schneefall',
        77 => 'Schneekörner',
        80 => 'Leichte Regenschauer',
        81 => 'Mäßige Regenschauer',
        82 => 'Starke Regenschauer',
        85 => 'Leichte Schneeschauer',
        86 => 'Starke Schneeschauer',
        95 => 'Gewitter',
        96 => 'Gewitter mit leichtem Hagel',
        99 => 'Gewitter mit starkem Hagel',
    ];

    public function pullSource(OrganizationEnvironmentSource $source): ?OrganizationEnvironmentSnapshot
    {
        $items = match ($source->source_type) {
            'rss' => $this->fetchRss($source),
            'weather' => $this->fetchWeather($source),
            'health_incidence' => $this->fetchHealthIncidence($source),
            default => [],
        };

        if (empty($items)) {
            $source->update(['last_pulled_at' => now()]);

            return null;
        }

        if (in_array($source->source_type, ['weather', 'health_incidence'])) {
            return $this->buildStructuredSnapshot($source, $items);
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

    public function fetchWeather(OrganizationEnvironmentSource $source): array
    {
        $config = $source->config;
        $latitude = $config['latitude'] ?? null;
        $longitude = $config['longitude'] ?? null;

        if (! $latitude || ! $longitude) {
            Log::warning('EnvironmentPullService: Weather source missing coordinates', ['source_id' => $source->id]);

            return [];
        }

        try {
            $response = Http::timeout(30)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,weather_code,relative_humidity_2m,precipitation,wind_speed_10m',
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weather_code',
                'timezone' => 'Europe/Berlin',
                'forecast_days' => 7,
            ]);

            if (! $response->successful()) {
                Log::warning('EnvironmentPullService: Weather API failed', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();
            $current = $data['current'] ?? [];
            $daily = $data['daily'] ?? [];

            $items = [];

            // Current weather as first item
            $items[] = [
                'type' => 'current',
                'temperature' => $current['temperature_2m'] ?? null,
                'weather_code' => $current['weather_code'] ?? null,
                'humidity' => $current['relative_humidity_2m'] ?? null,
                'precipitation' => $current['precipitation'] ?? null,
                'wind_speed' => $current['wind_speed_10m'] ?? null,
            ];

            // 7-day forecast
            $dates = $daily['time'] ?? [];
            $tempMax = $daily['temperature_2m_max'] ?? [];
            $tempMin = $daily['temperature_2m_min'] ?? [];
            $precip = $daily['precipitation_sum'] ?? [];
            $codes = $daily['weather_code'] ?? [];

            for ($i = 0; $i < count($dates); $i++) {
                $items[] = [
                    'type' => 'forecast',
                    'date' => $dates[$i],
                    'temp_max' => $tempMax[$i] ?? null,
                    'temp_min' => $tempMin[$i] ?? null,
                    'precipitation' => $precip[$i] ?? null,
                    'weather_code' => $codes[$i] ?? null,
                ];
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('EnvironmentPullService: Weather fetch exception', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function fetchHealthIncidence(OrganizationEnvironmentSource $source): array
    {
        $config = $source->config;
        $datasets = $config['datasets'] ?? [];
        $region = $config['region'] ?? 'Bundesweit';

        if (empty($datasets)) {
            Log::warning('EnvironmentPullService: Health source missing datasets config', ['source_id' => $source->id]);

            return [];
        }

        $items = [];

        try {
            if (in_array('grippeweb', $datasets)) {
                $items = array_merge($items, $this->fetchGrippeWebData($source, $region));
            }

            if (in_array('covid_are', $datasets)) {
                $items = array_merge($items, $this->fetchCovidAreData($source, $region));
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('EnvironmentPullService: Health incidence fetch exception', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function fetchGrippeWebData(OrganizationEnvironmentSource $source, string $region): array
    {
        $url = 'https://raw.githubusercontent.com/robert-koch-institut/GrippeWeb_Daten_des_Wochenberichts/main/GrippeWeb_Daten_des_Wochenberichts.tsv';

        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            Log::warning('EnvironmentPullService: GrippeWeb fetch failed', [
                'source_id' => $source->id,
                'status' => $response->status(),
            ]);

            return [];
        }

        $lines = explode("\n", trim($response->body()));
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines), "\t");
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, "\t");
            if (count($fields) === count($header)) {
                $rows[] = array_combine($header, $fields);
            }
        }

        // Filter by region and age group "00+" (total)
        $filtered = array_filter($rows, function ($row) use ($region) {
            $rowRegion = $row['Region'] ?? $row['region'] ?? '';
            $ageGroup = $row['Altersgruppe'] ?? $row['altersgruppe'] ?? '';

            return stripos($rowRegion, $region) !== false && $ageGroup === '00+';
        });

        if (empty($filtered)) {
            return [];
        }

        // Sort by calendar week descending
        usort($filtered, function ($a, $b) {
            $kwA = $a['Kalenderwoche'] ?? $a['kalenderwoche'] ?? '';
            $kwB = $b['Kalenderwoche'] ?? $b['kalenderwoche'] ?? '';

            return strcmp($kwB, $kwA);
        });

        $items = [];

        foreach (['ARE', 'ILI'] as $diseaseType) {
            $diseaseRows = array_values(array_filter($filtered, function ($row) use ($diseaseType) {
                $type = $row['Erkrankung'] ?? $row['erkrankung'] ?? '';

                return strtoupper($type) === $diseaseType;
            }));

            if (empty($diseaseRows)) {
                continue;
            }

            $current = $diseaseRows[0];
            $previous = $diseaseRows[1] ?? null;

            $incidence = (float) str_replace(',', '.', $current['Inzidenz'] ?? $current['inzidenz'] ?? '0');
            $prevIncidence = $previous ? (float) str_replace(',', '.', $previous['Inzidenz'] ?? $previous['inzidenz'] ?? '0') : null;
            $calendarWeek = $current['Kalenderwoche'] ?? $current['kalenderwoche'] ?? '';

            $trend = 'stable';
            $changePct = null;
            if ($prevIncidence !== null && $prevIncidence > 0) {
                $changePct = round(($incidence - $prevIncidence) / $prevIncidence * 100, 1);
                if ($changePct > 10) {
                    $trend = 'rising';
                } elseif ($changePct < -10) {
                    $trend = 'falling';
                }
            }

            $items[] = [
                'type' => 'health_incidence',
                'disease' => strtolower($diseaseType),
                'source_dataset' => 'grippeweb',
                'incidence' => $incidence,
                'previous' => $prevIncidence,
                'trend' => $trend,
                'change_pct' => $changePct,
                'calendar_week' => $calendarWeek,
                'region' => $region,
            ];
        }

        return $items;
    }

    protected function fetchCovidAreData(OrganizationEnvironmentSource $source, string $region): array
    {
        $url = 'https://raw.githubusercontent.com/robert-koch-institut/COVID-ARE-Konsultationsinzidenz/main/COVID-ARE-Konsultationsinzidenz.csv';

        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            Log::warning('EnvironmentPullService: COVID-ARE fetch failed', [
                'source_id' => $source->id,
                'status' => $response->status(),
            ]);

            return [];
        }

        $lines = explode("\n", trim($response->body()));
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines), ',');
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, ',');
            if (count($fields) === count($header)) {
                $rows[] = array_combine($header, $fields);
            }
        }

        // Filter by region
        $filtered = array_filter($rows, function ($row) use ($region) {
            $rowRegion = $row['Region'] ?? $row['region'] ?? $row['Bundesland'] ?? '';

            return stripos($rowRegion, $region) !== false;
        });

        if (empty($filtered)) {
            return [];
        }

        // Sort by calendar week descending
        usort($filtered, function ($a, $b) {
            $kwA = $a['Kalenderwoche'] ?? $a['kalenderwoche'] ?? $a['calendar_week'] ?? '';
            $kwB = $b['Kalenderwoche'] ?? $b['kalenderwoche'] ?? $b['calendar_week'] ?? '';

            return strcmp($kwB, $kwA);
        });

        $current = $filtered[0];
        $previous = $filtered[1] ?? null;

        $incidence = (float) str_replace(',', '.', $current['Inzidenz'] ?? $current['inzidenz'] ?? $current['ARE_Konsultationsinzidenz'] ?? '0');
        $prevIncidence = $previous ? (float) str_replace(',', '.', $previous['Inzidenz'] ?? $previous['inzidenz'] ?? $previous['ARE_Konsultationsinzidenz'] ?? '0') : null;
        $calendarWeek = $current['Kalenderwoche'] ?? $current['kalenderwoche'] ?? $current['calendar_week'] ?? '';

        $trend = 'stable';
        $changePct = null;
        if ($prevIncidence !== null && $prevIncidence > 0) {
            $changePct = round(($incidence - $prevIncidence) / $prevIncidence * 100, 1);
            if ($changePct > 10) {
                $trend = 'rising';
            } elseif ($changePct < -10) {
                $trend = 'falling';
            }
        }

        return [[
            'type' => 'health_incidence',
            'disease' => 'covid_are',
            'source_dataset' => 'covid_are',
            'incidence' => $incidence,
            'previous' => $prevIncidence,
            'trend' => $trend,
            'change_pct' => $changePct,
            'calendar_week' => $calendarWeek,
            'region' => $region,
        ]];
    }

    protected function buildStructuredSnapshot(OrganizationEnvironmentSource $source, array $items): OrganizationEnvironmentSnapshot
    {
        $sourceType = $source->source_type;

        if ($sourceType === 'weather') {
            $metrics = $this->buildWeatherMetrics($source, $items);
            $summary = $this->buildWeatherSummary($source, $metrics);
        } else {
            $metrics = $this->buildHealthMetrics($source, $items);
            $summary = $this->buildHealthSummary($source, $metrics);
        }

        $snapshot = OrganizationEnvironmentSnapshot::create([
            'team_id' => $source->team_id,
            'source_id' => $source->id,
            'snapshot_date' => now()->toDateString(),
            'metrics' => $metrics,
            'summary' => $summary,
            'raw_items' => $items,
        ]);

        $source->update(['last_pulled_at' => now()]);

        return $snapshot;
    }

    protected function buildWeatherMetrics(OrganizationEnvironmentSource $source, array $items): array
    {
        $location = $source->config['location_name'] ?? ($source->config['latitude'] . ',' . $source->config['longitude']);

        $current = null;
        $forecast = [];

        foreach ($items as $item) {
            if ($item['type'] === 'current') {
                $current = [
                    'temperature' => $item['temperature'],
                    'weather_code' => $item['weather_code'],
                    'humidity' => $item['humidity'],
                    'precipitation' => $item['precipitation'],
                    'wind_speed' => $item['wind_speed'],
                ];
            } elseif ($item['type'] === 'forecast') {
                $forecast[] = [
                    'date' => $item['date'],
                    'temp_max' => $item['temp_max'],
                    'temp_min' => $item['temp_min'],
                    'precipitation' => $item['precipitation'],
                    'weather_code' => $item['weather_code'],
                ];
            }
        }

        $tempMaxValues = array_column($forecast, 'temp_max');
        $tempMinValues = array_column($forecast, 'temp_min');
        $precipValues = array_column($forecast, 'precipitation');

        return [
            'source_type' => 'weather',
            'location' => $location,
            'current' => $current,
            'forecast' => $forecast,
            'temp_avg_7d' => ! empty($tempMaxValues) && ! empty($tempMinValues)
                ? round((array_sum($tempMaxValues) + array_sum($tempMinValues)) / (count($tempMaxValues) + count($tempMinValues)), 1)
                : null,
            'precipitation_total_7d' => ! empty($precipValues) ? round(array_sum($precipValues), 1) : null,
        ];
    }

    protected function buildWeatherSummary(OrganizationEnvironmentSource $source, array $metrics): string
    {
        $location = $metrics['location'] ?? '?';
        $current = $metrics['current'] ?? [];
        $forecast = $metrics['forecast'] ?? [];

        $temp = $current['temperature'] ?? '?';
        $code = $current['weather_code'] ?? 0;
        $weatherDesc = self::WMO_CODES[$code] ?? "Code {$code}";

        $date = now()->format('d.m.Y');

        $parts = ["{$location}, {$date}: {$temp}°C ({$weatherDesc})."];

        if (! empty($forecast)) {
            $allMin = array_column($forecast, 'temp_min');
            $allMax = array_column($forecast, 'temp_max');
            $minTemp = ! empty($allMin) ? round(min($allMin)) : '?';
            $maxTemp = ! empty($allMax) ? round(max($allMax)) : '?';
            $totalPrecip = $metrics['precipitation_total_7d'] ?? 0;
            $parts[] = "7-Tage-Trend: {$minTemp}–{$maxTemp}°C, {$totalPrecip}mm Niederschlag gesamt.";
        }

        return implode(' ', $parts);
    }

    protected function buildHealthMetrics(OrganizationEnvironmentSource $source, array $items): array
    {
        $region = $source->config['region'] ?? 'Bundesweit';
        $calendarWeek = null;
        $diseases = [];

        foreach ($items as $item) {
            $disease = $item['disease'] ?? 'unknown';
            $diseases[$disease] = [
                'incidence' => $item['incidence'],
                'previous' => $item['previous'],
                'trend' => $item['trend'],
                'change_pct' => $item['change_pct'],
            ];
            if (! $calendarWeek) {
                $calendarWeek = $item['calendar_week'] ?? null;
            }
        }

        return [
            'source_type' => 'health_incidence',
            'region' => $region,
            'calendar_week' => $calendarWeek,
            'diseases' => $diseases,
        ];
    }

    protected function buildHealthSummary(OrganizationEnvironmentSource $source, array $metrics): string
    {
        $kw = $metrics['calendar_week'] ?? '?';
        $region = $metrics['region'] ?? '?';
        $diseases = $metrics['diseases'] ?? [];

        $trendSymbols = ['rising' => '↑', 'falling' => '↓', 'stable' => '→'];
        $parts = [];

        foreach ($diseases as $name => $data) {
            $incidence = number_format($data['incidence'], 0, ',', '.');
            $changePct = $data['change_pct'] !== null ? sprintf('%+.1f%%', $data['change_pct']) : '?';
            $symbol = $trendSymbols[$data['trend']] ?? '→';
            $parts[] = strtoupper($name) . " {$incidence} ({$changePct} {$symbol})";
        }

        $diseaseSummary = implode(', ', $parts);

        return "{$kw} {$region}: {$diseaseSummary}.";
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

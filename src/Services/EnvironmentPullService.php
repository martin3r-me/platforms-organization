<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;

class EnvironmentPullService
{
    public function pullSource(OrganizationEnvironmentSource $source): ?OrganizationEnvironmentSnapshot
    {
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

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 1024,
                'temperature' => 0,
                'system' => 'Du extrahierst strukturierte Informationen aus Nachrichten-Feeds. Antworte ausschließlich mit validem JSON in diesem Format: {"summary": "2-3 Sätze Zusammenfassung", "sentiment_score": <-1.0 bis 1.0>, "relevance_score": <0.0 bis 1.0>, "topics": ["topic1", "topic2"]}. sentiment_score: -1 = sehr negativ, 0 = neutral, 1 = sehr positiv. relevance_score: 0 = irrelevant, 1 = hochrelevant für die angegebene Kategorie.',
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

<?php

namespace Platform\Organization\Support;

use Platform\Organization\Contracts\AgentWorkSource;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Registry des Arbeitseingangs der Agenten (lose Kopplung, analog StrategySources): Module registrieren
 * ihre Quelle beim Booten. Ohne Quelle ist der Eingang leer – die Organisation bleibt voll lauffähig.
 * Fällt eine Quelle aus, steht sie mit ok=false in `sources` (Lage unbekannt, nicht „nichts zu tun“).
 */
class AgentWorkSources
{
    /** @var array<string, AgentWorkSource> */
    private static array $sources = [];

    public static function register(AgentWorkSource $source): void
    {
        self::$sources[$source->key()] = $source;
    }

    public static function forget(string $key): void
    {
        unset(self::$sources[$key]);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::$sources);
    }

    /**
     * Arbeitseingang eines Agenten, dringendstes zuerst.
     *
     * @return array{items: list<array<string,mixed>>, sources: list<array{source:string, ok:bool, error?:string}>}
     */
    public static function forAgent(OrganizationEntity $agent): array
    {
        $items = [];
        $sources = [];
        foreach (self::$sources as $key => $source) {
            try {
                foreach ($source->workFor($agent) as $item) {
                    $count = (int) ($item['count'] ?? 0);
                    if ($count < 1 || empty($item['key']) || empty($item['tool'])) {
                        continue;
                    }
                    $items[] = [
                        'source' => $key,
                        'key' => (string) $item['key'],
                        'text' => (string) ($item['text'] ?? ''),
                        'count' => $count,
                        'weight' => max(0.0, min(1.0, (float) ($item['weight'] ?? 0.5))),
                        'tool' => (string) $item['tool'],
                        'how' => $item['how'] ?? null,
                        'oldest_at' => $item['oldest_at'] ?? null,
                    ];
                }
                $sources[] = ['source' => $key, 'ok' => true];
            } catch (\Throwable $e) {
                report($e);
                $sources[] = ['source' => $key, 'ok' => false, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…')];
            }
        }
        usort($items, fn ($a, $b) => $b['weight'] <=> $a['weight'] ?: strcmp((string) $a['oldest_at'], (string) $b['oldest_at']));

        return ['items' => $items, 'sources' => $sources];
    }
}

<?php

namespace Platform\Organization\Support;

use Platform\Organization\Contracts\AgentFieldSource;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Registry des Arbeitsfelds der Agenten (lose Kopplung, analog AgentWorkSources): Module registrieren ihre
 * Quelle beim Booten. Ohne Quelle ist das Feld leer — die Organisation bleibt voll lauffähig. Fällt eine
 * Quelle aus, steht sie mit ok=false in `sources` (Feld unbekannt, nicht „nichts da“).
 */
class AgentFieldSources
{
    /** @var array<string, AgentFieldSource> */
    private static array $sources = [];

    public static function register(AgentFieldSource $source): void
    {
        self::$sources[$source->key()] = $source;
    }

    public static function forget(string $key): void
    {
        unset(self::$sources[$key]);
    }

    /**
     * @return array{units: list<array<string,mixed>>, objects: list<array<string,mixed>>, creatable: array<string,string>,
     *     sources: list<array{source:string, ok:bool, error?:string}>}
     */
    public static function forAgent(OrganizationEntity $agent, ?AgentDomain $domain = null): array
    {
        $domain ??= AgentDomain::of($agent);
        $objects = [];
        $creatable = [];
        $sources = [];
        foreach (self::$sources as $key => $source) {
            try {
                foreach ($source->fieldFor($agent, $domain) as $o) {
                    if (empty($o['type']) || empty($o['id'])) {
                        continue;
                    }
                    $objects[] = ['source' => $key] + $o;
                }
                $creatable += $source->creatable();
                $sources[] = ['source' => $key, 'ok' => true];
            } catch (\Throwable $e) {
                report($e);
                $sources[] = ['source' => $key, 'ok' => false, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…')];
            }
        }

        return ['units' => $domain->units(), 'objects' => $objects, 'creatable' => $creatable, 'sources' => $sources];
    }

    /** Die Quelle, die diese Objektart anlegt — oder null. */
    public static function creatorOf(string $type): ?AgentFieldSource
    {
        foreach (self::$sources as $source) {
            if (array_key_exists($type, $source->creatable())) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Ist das dieselbe Arbeit? Titel normalisiert (klein, nur Buchstaben/Ziffern) — gleich, einer im anderen
     * enthalten oder ≥ 80 % ähnlich. Eine Regel für alle Quellen, damit „schon da“ überall gleich gilt.
     */
    public static function sameWork(string $a, string $b): bool
    {
        $norm = fn (string $s) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)) ?? '');
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        if (mb_strlen($a) >= 12 && mb_strlen($b) >= 12 && (str_contains($a, $b) || str_contains($b, $a))) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 80.0;
    }
}

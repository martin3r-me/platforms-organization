<?php

namespace Platform\Organization\Strategy;

/**
 * Die fünf Kapitel einer Carrier-Strategie — feste Reihenfolge (= Deklarations-
 * reihenfolge). Das kanonische Vokabular des Strategie-Blueprints.
 *
 * Bogen: wer wir sind → wohin → was wir sehen → worauf wir fokussieren →
 * wie es über die Zeit läuft.
 */
enum StrategyChapter: string
{
    case Mission = 'mission';
    case Vision = 'vision';
    case Regnose = 'regnose';
    case FocusAreas = 'focus_areas';
    case TransformationMap = 'transformation_map';

    /** 1-basierte Reihenfolge (Deklarationsreihenfolge). */
    public function order(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function label(): string
    {
        return match ($this) {
            self::Mission           => 'Mission',
            self::Vision            => 'Vision',
            self::Regnose           => 'Regnose',
            self::FocusAreas        => 'Fokusräume',
            self::TransformationMap => 'Transformation-Map',
        };
    }

    /** md = Prosa-Feld · container = strukturierte Sammlung · derived = abgeleitet. */
    public function type(): string
    {
        return match ($this) {
            self::Mission, self::Vision, self::Regnose => 'md',
            self::FocusAreas                           => 'container',
            self::TransformationMap                    => 'derived',
        };
    }

    /** Muss für eine „vollständige" Strategie befüllt sein? (derived nie.) */
    public function required(): bool
    {
        return $this !== self::TransformationMap;
    }

    public function guidance(): string
    {
        return match ($this) {
            self::Mission           => 'Wer wir sind — der Auftrag in ein, zwei Sätzen.',
            self::Vision            => 'Wohin — das Zielbild der Organisation.',
            self::Regnose           => 'Das Zukunfts-Narrativ: rückblickend aus der Zukunft erzählt.',
            self::FocusAreas        => 'Wo wir ansetzen — je Fokusraum Zielbilder, Hindernisse, Meilensteine.',
            self::TransformationMap => 'Ergibt sich aus Meilenstein × Quartal über alle Fokusräume — nichts zu schreiben.',
        };
    }
}

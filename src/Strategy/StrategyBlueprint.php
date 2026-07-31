<?php

namespace Platform\Organization\Strategy;

/**
 * Das kanonische Strategie-Blueprint — die EINE deklarative Vorgabe, was eine
 * vollständige Carrier-Strategie enthält, in welcher Reihenfolge, mit welchen
 * Regeln. Oberflächen-unabhängig: UI, MCP-Tools und API prüfen alle hiergegen.
 *
 * Ein Blueprint, vier Wirkungen: Vorgabe/Reihenfolge · Validierung/Vollständig-
 * keit · Render-Reihenfolge (Tab/Public/PDF) · Vollständigkeits-Signal.
 *
 * Guard Rails aufs Skelett; Blätter tragen gebundenen Rich-Text (md). Einziger
 * harter atomarer Zwang: Meilenstein → Quartal (sonst keine Transformation-Map).
 *
 * Fix, nicht konfigurierbar (bewusst — eine richtige Form für alle).
 */
class StrategyBlueprint
{
    /** @return StrategyChapter[] in kanonischer Reihenfolge. */
    public static function chapters(): array
    {
        return StrategyChapter::cases();
    }

    /**
     * Sub-Struktur eines Fokusraums (Kapitel 4). Reihenfolge = Deklaration.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function focusAreaNodes(): array
    {
        return [
            ['key' => 'zielbilder',  'label' => 'Zielbilder',  'type' => 'md',      'required' => false, 'cardinality' => '0..n'],
            ['key' => 'hindernisse', 'label' => 'Hindernisse', 'type' => 'md',      'required' => false, 'cardinality' => '0..n'],
            [
                'key' => 'meilensteine', 'label' => 'Meilensteine', 'type' => 'md', 'required' => true, 'cardinality' => '1..n',
                'children' => [
                    // Der einzige harte Pflicht-Atomwert: die Quartalszuordnung.
                    ['key' => 'quarter', 'label' => 'Quartal', 'type' => 'quarter', 'required' => true, 'cardinality' => '1'],
                ],
            ],
        ];
    }

    /**
     * Vollständige, serialisierbare Spec (für UI/MCP/API-Guidance).
     *
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $chapters = [];
        foreach (self::chapters() as $c) {
            $node = [
                'key'         => $c->value,
                'label'       => $c->label(),
                'order'       => $c->order(),
                'type'        => $c->type(),
                'required'    => $c->required(),
                'cardinality' => self::cardinality($c),
                'guidance'    => $c->guidance(),
            ];
            if ($c === StrategyChapter::FocusAreas) {
                $node['children'] = self::focusAreaNodes();
            }
            $chapters[] = $node;
        }

        return ['chapters' => $chapters];
    }

    public static function cardinality(StrategyChapter $c): string
    {
        return match ($c) {
            StrategyChapter::FocusAreas        => '1..n',
            StrategyChapter::TransformationMap => '—',
            default                            => '1',
        };
    }
}

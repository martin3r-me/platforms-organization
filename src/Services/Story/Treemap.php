<?php

namespace Platform\Organization\Services\Story;

/**
 * Squarified Treemap (Bruls/Huizing/van Wijk): verteilt Werte als möglichst quadratische Rechtecke
 * auf eine Fläche. Für die Karte der Organisation — Fläche = gebuchte Zeit.
 *
 * Gerechnet wird in einer virtuellen Fläche mit dem Seitenverhältnis des Containers, damit
 * „quadratisch" auch auf dem Bildschirm quadratisch ist; zurück kommen Prozentwerte.
 */
class Treemap
{
    /**
     * @param  array  $items  je Eintrag mindestens ['value' => float > 0]; Reihenfolge egal
     * @return array  dieselben Einträge mit 'rect' => [x, y, w, h] in Prozent des Containers
     */
    public static function layout(array $items, float $aspect = 3.0): array
    {
        $items = array_values(array_filter($items, fn ($i) => ($i['value'] ?? 0) > 0));
        usort($items, fn ($a, $b) => $b['value'] <=> $a['value']);
        if ($items === []) {
            return [];
        }

        $W = 100 * $aspect;
        $H = 100.0;
        $total = array_sum(array_column($items, 'value'));
        foreach ($items as &$i) {
            $i['_area'] = $i['value'] / $total * $W * $H;
        }
        unset($i);

        $out = [];
        $x = 0.0; $y = 0.0; $w = $W; $h = $H;
        $row = [];
        while ($items !== []) {
            $short = min($w, $h);
            $candidate = array_merge($row, [$items[0]]);
            if ($row === [] || self::worst($candidate, $short) <= self::worst($row, $short)) {
                $row = $candidate;
                array_shift($items);
                continue;
            }
            [$placed, $x, $y, $w, $h] = self::place($row, $x, $y, $w, $h);
            $out = array_merge($out, $placed);
            $row = [];
        }
        if ($row !== []) {
            [$placed] = self::place($row, $x, $y, $w, $h);
            $out = array_merge($out, $placed);
        }

        return array_map(function ($i) use ($W, $H) {
            [$x, $y, $w, $h] = $i['_rect'];
            unset($i['_rect'], $i['_area']);
            $i['rect'] = [round($x / $W * 100, 3), round($y / $H * 100, 3), round($w / $W * 100, 3), round($h / $H * 100, 3)];

            return $i;
        }, $out);
    }

    private static function worst(array $row, float $side): float
    {
        $areas = array_column($row, '_area');
        $sum = array_sum($areas);
        if ($sum <= 0 || $side <= 0) {
            return INF;
        }

        return max(($side ** 2) * max($areas) / ($sum ** 2), ($sum ** 2) / (($side ** 2) * min($areas)));
    }

    /** Legt eine Reihe entlang der kürzeren Seite und gibt die Restfläche zurück. */
    private static function place(array $row, float $x, float $y, float $w, float $h): array
    {
        $sum = array_sum(array_column($row, '_area'));
        $placed = [];
        if ($w >= $h) {
            $colW = $sum / $h;
            $cy = $y;
            foreach ($row as $i) {
                $ih = $i['_area'] / $colW;
                $i['_rect'] = [$x, $cy, $colW, $ih];
                $placed[] = $i;
                $cy += $ih;
            }

            return [$placed, $x + $colW, $y, $w - $colW, $h];
        }

        $rowH = $sum / $w;
        $cx = $x;
        foreach ($row as $i) {
            $iw = $i['_area'] / $rowH;
            $i['_rect'] = [$cx, $y, $iw, $rowH];
            $placed[] = $i;
            $cx += $iw;
        }

        return [$placed, $x, $y + $rowH, $w, $h - $rowH];
    }
}

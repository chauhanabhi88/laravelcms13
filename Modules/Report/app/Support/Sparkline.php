<?php

declare(strict_types=1);

namespace Modules\Report\Support;

/**
 * Server-rendered SVG line geometry (KPI sparklines, the supplier sales trend): no script
 * needed. Missing values (null) are drawn as 0.
 */
final class Sparkline
{
    /**
     * "x,y x,y …" for an SVG polyline in a width × height box (y grows downwards), with
     * $padding kept free at the top and bottom.
     *
     * @param  list<float|int|null>  $values
     */
    public static function points(array $values, float $width = 120, float $height = 32, float $padding = 2): string
    {
        $values = array_map(fn ($value): float => (float) ($value ?? 0), array_values($values));
        $count = count($values);

        if ($count === 0) {
            return '';
        }

        $max = max($values);
        $min = min(0.0, min($values));
        $span = $max - $min ?: 1.0;
        $step = $count > 1 ? $width / ($count - 1) : 0;

        $points = [];
        foreach ($values as $index => $value) {
            $x = $count > 1 ? $index * $step : $width / 2;
            $y = $height - $padding - (($value - $min) / $span) * ($height - 2 * $padding);
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    }

    /**
     * Whether a series has anything worth drawing.
     *
     * @param  list<float|int|null>  $values
     */
    public static function hasData(array $values): bool
    {
        return count($values) > 1 && array_filter($values, fn ($value): bool => (float) ($value ?? 0) != 0.0) !== [];
    }
}

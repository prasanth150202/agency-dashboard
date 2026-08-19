<?php

namespace App\Support;

class Metrics
{
    /**
     * Percentage change between two numeric values, rounded to 1 decimal.
     */
    public static function percentChange(int|float $current, int|float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

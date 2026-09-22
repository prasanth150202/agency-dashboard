<?php

namespace App\Support;

/**
 * Decimal-safe money arithmetic for stored commission figures: amounts are
 * handled as integer minor units (cents) and percentages as integer
 * hundredths of a percent, so no floating-point value ever decides a
 * stored amount. No dependency on bcmath.
 */
final class DecimalMoney
{
    /** "3", "3.5", "3.50", 3.5 -> 350. Amounts here are DECIMAL(x,2) at source. */
    public static function toCents(string|int|float $amount): int
    {
        $string = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $string, $m)) {
            throw new \InvalidArgumentException("Not a decimal amount: {$string}");
        }

        $fraction = str_pad(substr($m[3] ?? '', 0, 2), 2, '0');
        $cents = ((int) $m[2]) * 100 + (int) $fraction;

        // Half-up on a third decimal, if a source ever carries one.
        if (isset($m[3]) && strlen($m[3]) > 2 && (int) $m[3][2] >= 5) {
            $cents++;
        }

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** "30", "30.5", 30.0 -> 3000 (hundredths of a percent). */
    public static function percentToHundredths(string|int|float $percent): int
    {
        return self::toCents($percent);
    }

    /** cents x percent, rounded half away from zero to the cent. */
    public static function percentOf(int $cents, int $percentHundredths): int
    {
        $product = $cents * $percentHundredths;
        $sign = $product < 0 ? -1 : 1;

        return $sign * intdiv(abs($product) + 5000, 10000);
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}

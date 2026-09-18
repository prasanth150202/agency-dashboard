<?php

namespace App\Support;

/**
 * The one place a currency code turns into a symbol or a formatted
 * string. Nothing in the Commission/Payout surface should hardcode ₹ —
 * currency is a per-agency setting (organisation_settings.currency),
 * not a constant.
 */
class Currency
{
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    public static function symbol(?string $code): string
    {
        return self::SYMBOLS[strtoupper($code ?? 'INR')] ?? strtoupper($code ?? 'INR').' ';
    }

    public static function format(float|string|null $amount, ?string $code = 'INR'): string
    {
        return self::symbol($code).number_format((float) $amount, 2);
    }
}

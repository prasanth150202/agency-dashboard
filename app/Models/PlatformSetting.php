<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide finance settings — controlled by BRIX, not the agency.
 * Single row; use PlatformSetting::current() rather than querying directly.
 */
class PlatformSetting extends Model
{
    protected $fillable = [
        'minimum_payout_amount',
        'commission_holding_period_days',
    ];

    protected $casts = [
        'minimum_payout_amount' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}

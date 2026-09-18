<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Platform-wide finance settings — controlled by BRIX, not the partner.
 * Backed by the real, admin-controlled `app_settings` key-value store
 * rather than a dedicated table. Use PlatformSetting::current() rather
 * than reading app_settings directly.
 */
class PlatformSetting
{
    public function __construct(
        public readonly float $minimum_payout_amount,
        public readonly int $commission_holding_period_days,
    ) {}

    public static function current(): self
    {
        $rows = DB::table('app_settings')
            ->whereIn('setting_key', ['minimum_payout_amount', 'commission_holding_period_days'])
            ->pluck('value', 'setting_key');

        return new self(
            minimum_payout_amount: (float) ($rows['minimum_payout_amount'] ?? 1000),
            commission_holding_period_days: (int) ($rows['commission_holding_period_days'] ?? 7),
        );
    }
}

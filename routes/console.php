<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Records newly verified BRIX revenue (subscription via Shopify's Partner
// API, usage via cartninja) and accrues commission from it daily, so a
// lead's Verified Revenue/Commission Earned stay current without anyone
// running referrals:accrue-commissions by hand. Payout itself stays a
// separate, manual, monthly step in BRIX Super Admin — this only ever
// produces AVAILABLE-after-holding-period commission for a payout to
// later claim, never a payout itself.
Schedule::command('referrals:accrue-commissions --write')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/accrue-commissions.log'));

// Keeps the admin Stores page's dev/test detection instant — without
// this, the cache only warms lazily on whoever's page load happens to
// hit an expired entry, which measured at ~65s cold for all 64 shops.
Schedule::command('brix:warm-store-domain-cache')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/warm-store-domain-cache.log'));

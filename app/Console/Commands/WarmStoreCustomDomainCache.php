<?php

namespace App\Console\Commands;

use App\Services\Brix\CustomDomainCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-warms CustomDomainCheck's cache for every real cartninja shop, so
 * the admin Stores page's dev/test detection never makes an admin wait
 * through up to 64 live, sequential external HTTP checks on page load —
 * confirmed to take ~65s cold vs instant once cached.
 */
class WarmStoreCustomDomainCache extends Command
{
    protected $signature = 'brix:warm-store-domain-cache';

    protected $description = 'Pre-warm the custom-domain check cache for every cartninja shop (dev/test store detection)';

    public function handle(): int
    {
        $domains = DB::connection('cartninja')->table('shops')->pluck('shop_domain');

        $this->withProgressBar($domains, function (string $domain) {
            CustomDomainCheck::hasCustomDomain($domain);
        });

        $this->newLine(2);
        $this->info("Warmed {$domains->count()} shops.");

        return self::SUCCESS;
    }
}

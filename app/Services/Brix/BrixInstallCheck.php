<?php

namespace App\Services\Brix;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the live BRIX Shopify backend (int.thebrix.io,
 * php_backend/store_install_status.php) whether BRIX is actually
 * installed and active on a shop.
 *
 * This is the authoritative source for Shopify install state.
 * brix_superadmin's own stores.installation_status only mirrors it when
 * an install webhook happens to land during a live agency_store_onboarding
 * attempt — a store the agency connects that was already installed
 * beforehand never triggers that write, which is what leaves merchants
 * bounced back to the App Store for an app they already have.
 *
 * Never throws. An unreachable or misconfigured backend returns null
 * ("don't know") so callers keep their existing safe fallback (send the
 * merchant through the App Store again — idempotent on Shopify's side)
 * rather than guessing "installed".
 */
class BrixInstallCheck
{
    public static function isInstalled(string $shopDomain): ?bool
    {
        $secret = (string) config('services.shopify.internal_secret');
        $base = rtrim((string) config('services.shopify.backend_url'), '/');

        if ($secret === '' || $base === '') {
            Log::warning('BrixInstallCheck: backend URL or internal secret not configured.');

            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Internal-Secret' => $secret])
                ->connectTimeout(3)
                ->timeout(5)
                ->get("{$base}/store_install_status.php", [
                    'shop_domain' => strtolower($shopDomain),
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                Log::warning('BrixInstallCheck: unexpected response', [
                    'shop_domain' => $shopDomain,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return (bool) $response->json('data.installed');
        } catch (\Throwable $e) {
            Log::warning('BrixInstallCheck: request failed', [
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

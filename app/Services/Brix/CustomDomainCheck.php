<?php

namespace App\Services\Brix;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Whether a shop has a real custom domain connected, checked live against
 * the storefront itself — Shopify redirects the raw *.myshopify.com URL
 * to the merchant's connected domain when one exists, and leaves it on
 * *.myshopify.com otherwise. This is the one signal that's actually held
 * up under real counterexamples: the domain NAME alone can't tell a dev
 * store apart from a real one ("trils-5th1mqvj" is real, "doremon-
 * 9dmhittg" is a test store — same handle shape, opposite answers), but
 * whether Shopify redirects it anywhere else can.
 *
 * Not proof either way — a genuine, very new/small real store can still
 * have no custom domain connected, so this stays a signal, not a
 * certainty. Cached a day per shop so the admin Stores page never makes
 * up to 64 live external requests on every load.
 */
class CustomDomainCheck
{
    private const CACHE_TTL_HOURS = 24;

    /**
     * @return bool|null true = redirects to a real custom domain,
     *                    false = stays on *.myshopify.com (or another
     *                    *.myshopify.com handle — still not a custom
     *                    domain), null = couldn't be checked right now.
     */
    public static function hasCustomDomain(string $shopDomain): ?bool
    {
        return Cache::remember(
            'custom-domain-check:'.strtolower($shopDomain),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => self::check($shopDomain)
        );
    }

    private static function check(string $shopDomain): ?bool
    {
        // shop_domain always comes from cartninja (a trusted, internal
        // source — never user input here), but this guards against a
        // malformed row still forcing an outbound request to it.
        if (preg_match('/^[a-z0-9][a-z0-9.-]*\.myshopify\.com$/i', $shopDomain) !== 1) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; BrixStoreCheck/1.0)'])
                ->connectTimeout(3)
                ->timeout(6)
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get("https://{$shopDomain}");
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $finalHost = strtolower((string) $response->effectiveUri()?->getHost());

        if ($finalHost === '') {
            return null;
        }

        return ! Str::endsWith($finalHost, '.myshopify.com');
    }
}

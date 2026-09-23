<?php

namespace App\Services\Shopify;

use App\Services\Referral\ReferralAttribution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Turns whatever website an agency typed (https://coolgadgets.com,
 * www.coolgadgets.com/collections/x, coolgadgets.myshopify.com) into the
 * shop's permanent *.myshopify.com domain — or null when the site is not
 * a Shopify storefront. Never throws.
 *
 * The URL is user-supplied, so every host fetched (including redirect
 * hops) must resolve only to public IPs — otherwise this would be an SSRF
 * path into localhost / the private network / cloud metadata endpoints.
 */
class ShopifyStoreResolver
{
    private const MAX_BODY_BYTES = 1_000_000;

    public function resolve(string $website): ?string
    {
        $url = trim($website);
        $url = Str::contains($url, '://') ? $url : "https://{$url}";

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if ($shop = ReferralAttribution::normalizeShopDomain($host)) {
            return $shop;
        }

        $origin = "https://{$host}";

        return $this->fromMetaJson($origin) ?? $this->fromStorefront($origin);
    }

    /** Every Shopify storefront serves /meta.json with its permanent domain. */
    private function fromMetaJson(string $origin): ?string
    {
        $body = $this->fetch("{$origin}/meta.json");

        if ($body === null) {
            return null;
        }

        $json = json_decode($body, true);

        return is_array($json)
            ? ReferralAttribution::normalizeShopDomain($json['myshopify_domain'] ?? null)
            : null;
    }

    /** Fallback: the storefront's own `Shopify.shop = "x.myshopify.com"` bootstrap. */
    private function fromStorefront(string $origin): ?string
    {
        $body = $this->fetch($origin);

        if ($body === null) {
            return null;
        }

        $patterns = [
            '/Shopify\.shop\s*=\s*["\']([a-z0-9][a-z0-9\-]*\.myshopify\.com)["\']/i',
            '/"myshopify_domain"\s*:\s*"([a-z0-9][a-z0-9\-]*\.myshopify\.com)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $m) === 1) {
                return ReferralAttribution::normalizeShopDomain($m[1]);
            }
        }

        return null;
    }

    private function fetch(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! $this->isPublicHost($host)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(6)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; BrixLeadCheck/1.0)'])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['https', 'http'],
                        'on_redirect' => function ($request, $response, $uri) {
                            if (! $this->isPublicHost($uri->getHost())) {
                                throw new \RuntimeException('Redirect to a non-public host blocked.');
                            }
                        },
                    ],
                ])
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return substr($response->body(), 0, self::MAX_BODY_BYTES);
    }

    protected function isPublicHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}

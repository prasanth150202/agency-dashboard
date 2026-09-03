<?php

namespace App\Http\Controllers\Internal\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared shared-secret check + store-name guessing for the internal
 * (server-to-server) Shopify webhook controllers. Kept in one place so a
 * future hardening of the check (new header, replay protection, etc.)
 * can't be applied to one webhook controller and forgotten on the other.
 */
trait AuthorizesInternalWebhook
{
    private function authorize(Request $request): void
    {
        $expected = (string) config('services.shopify.internal_secret');

        if ($expected === '') {
            // Fail closed: an unconfigured secret must never silently
            // accept a well-known placeholder value.
            report(new \RuntimeException('AGENCY_DASHBOARD_INTERNAL_SECRET is not configured.'));
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Server misconfiguration.');
        }

        $provided = (string) $request->header('X-Internal-Secret', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthorized.');
        }
    }

    private function guessStoreName(string $shopDomain): string
    {
        $handle = Str::before($shopDomain, '.myshopify.com');

        return Str::of($handle)->replace('-', ' ')->title()->toString();
    }
}

<?php

namespace App\Services\Brix;

use Illuminate\Support\Facades\DB;

/**
 * Thin wrapper around brix_superadmin's existing rate_limit_hits table —
 * used to throttle store-connection actions (connect/authorize/activate)
 * per shop and per agency. Fails open: if brix_superadmin is briefly
 * unreachable, requests are allowed through rather than the whole flow
 * breaking (see section 22 of the store-connection spec).
 */
class RateLimitGuard
{
    public static function tooMany(string $key, int $maxAttempts, int $withinSeconds): bool
    {
        try {
            $count = DB::table('rate_limit_hits')
                ->where('rate_key', $key)
                ->where('created_at', '>=', now()->subSeconds($withinSeconds))
                ->count();

            return $count >= $maxAttempts;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function hit(string $key): void
    {
        try {
            DB::table('rate_limit_hits')->insert([
                'rate_key' => $key,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

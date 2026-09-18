<?php

namespace App\Services\Brix;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Pre-merge, this mirrored a brix_superadmin row onto a separate local
 * `stores` table. Since the single-database merge, App\Models\Store IS
 * the real row — there is nothing left to mirror into. This now only
 * touches the couple of fields (status, plan, last_active_at) that used
 * to get refreshed as a side effect of that sync, on every agency
 * authorize/activate/reconnect action.
 */
class LocalStoreSync
{
    public static function touch(Store $store, ?string $relationshipStatus): Store
    {
        $attributes = [
            'status' => match (true) {
                $store->installation_status === 'UNINSTALLED' => 'offline',
                $relationshipStatus === 'ACTIVE' => 'active',
                default => 'attention',
            },
            'last_active_at' => now(),
        ];

        $plan = self::resolvePlan($store->shop_domain);
        if ($plan !== null) {
            $attributes['plan'] = $plan;
        }

        $store->update($attributes);

        return $store;
    }

    /**
     * The real BRIX plan for a shop, read from the read-only `cartninja`
     * connection's `shops.plan_key` column — the Shopify app's own
     * canonical plan value. Never writes to that connection, and never
     * invents a plan: returns null (meaning "leave stores.plan alone")
     * when the shop has no row yet, the connection is unreachable, or
     * plan_key holds anything other than one of the three known values.
     */
    private static function resolvePlan(string $shopDomain): ?string
    {
        try {
            $planKey = DB::connection('cartninja')
                ->table('shops')
                ->where('shop_domain', $shopDomain)
                ->value('plan_key');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if ($planKey === null) {
            return null;
        }

        if (! array_key_exists($planKey, Store::PLAN_LABELS)) {
            report(new \RuntimeException("LocalStoreSync: unrecognized BRIX plan_key '{$planKey}' for {$shopDomain}"));

            return null;
        }

        return Store::PLAN_LABELS[$planKey];
    }
}

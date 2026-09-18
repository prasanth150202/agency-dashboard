<?php

namespace App\Services\Brix;

use App\Models\Brix\Store as BrixStore;
use App\Models\Organisation;
use App\Models\Store as LocalStore;
use App\Models\StoreModule;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors a brix_superadmin `stores` row (+ its agency_stores relationship
 * status) onto this app's own local `stores` table, so the existing
 * dashboard, module toggles, and payouts — all built against
 * \App\Models\Store — keep working unchanged once a store is connected.
 * brix_superadmin stays the source of truth; this is a read-through
 * cache, never written back from.
 */
class LocalStoreSync
{
    public static function sync(Organisation $organisation, BrixStore $brixStore, ?string $relationshipStatus): LocalStore
    {
        $attributes = [
            'organisation_id' => $organisation->id,
            'name' => $brixStore->store_name,
            'shop_domain' => $brixStore->shop_domain,
            'shopify_shop_id' => $brixStore->shopify_shop_id,
            'admin_url' => "https://{$brixStore->shop_domain}/admin",
            'installation_status' => $brixStore->installation_status,
            'authorization_status' => $brixStore->authorization_status,
            'agency_relationship_status' => $relationshipStatus,
            'status' => match (true) {
                $brixStore->installation_status === 'UNINSTALLED' => 'offline',
                $relationshipStatus === 'ACTIVE' => 'active',
                default => 'attention',
            },
            'last_active_at' => now(),
        ];

        // Only set 'plan' when a real, recognized value came back — an
        // unreachable cartninja connection, a shop with no `shops` row yet,
        // or an unrecognized plan_key must never overwrite whatever's
        // already stored (or silently invent one on first sync; the
        // 'plan' column's own migration default covers that case exactly
        // as it did before this mirroring existed).
        $plan = self::resolvePlan($brixStore->shop_domain);
        if ($plan !== null) {
            $attributes['plan'] = $plan;
        }

        $local = LocalStore::where('shop_domain', $brixStore->shop_domain)->first();

        if ($local) {
            $local->update($attributes);

            return $local;
        }

        $attributes['installed_at'] = $brixStore->installed_at ?? now();
        $local = LocalStore::create($attributes);

        foreach (array_keys(StoreModule::MODULES) as $module) {
            $local->modules()->create([
                'module' => $module,
                'status' => 'inactive',
                'last_updated_at' => null,
            ]);
        }

        return $local;
    }

    /**
     * The real BRIX plan for a shop, read from the existing read-only
     * `cartninja` connection's `shops.plan_key` column — the Shopify app's
     * own canonical plan value (Cart_ninja_combo1's plan-permissions.server.js
     * keeps it correct via its subscription webhook + periodic live
     * reconciliation against Shopify's own Billing API). This never writes
     * to that connection, and never invents a plan: returns null (meaning
     * "leave stores.plan alone") when the shop has no row yet, the
     * connection is unreachable, or plan_key holds anything other than one
     * of the three known values — those conditions are reported, not
     * guessed at.
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

        if (! array_key_exists($planKey, LocalStore::PLAN_LABELS)) {
            report(new \RuntimeException("LocalStoreSync: unrecognized BRIX plan_key '{$planKey}' for {$shopDomain}"));

            return null;
        }

        return LocalStore::PLAN_LABELS[$planKey];
    }
}

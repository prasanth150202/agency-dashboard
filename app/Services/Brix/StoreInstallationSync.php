<?php

namespace App\Services\Brix;

use App\Models\Brix\ActivityLog as BrixActivityLog;
use App\Models\Brix\AgencyStore;
use App\Models\Brix\Store as BrixStore;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a completed Shopify install of BRIX into brix_superadmin —
 * stores.installation_status = INSTALLED, plus a PENDING agency_stores
 * relationship — and mirrors it onto this app's local stores table.
 *
 * One code path, shared by:
 *  - the install webhook (AgencyShopifyWebhookController::storeInstalled),
 *    fired by the Shopify app's PHP backend right after OAuth, and
 *  - the on-demand reconcile in StoreConnectionController, for a store
 *    already installed before the agency started onboarding it (so the
 *    webhook either never fired or found no onboarding row to match).
 *
 * Shopify install + OAuth succeed together, so installation_status and
 * authorization_status flip together here. This never advances
 * relationship_status beyond creating the initial PENDING row (or
 * reviving a DISCONNECTED one) — authorize/activate is the agency's own
 * separate decision.
 */
class StoreInstallationSync
{
    /**
     * @return array{store: BrixStore, relationship_status: string}|null
     *         null when the shop already belongs to a different agency.
     */
    public static function mirror(string $shopDomain, int $agencyId, ?string $shopifyShopId = null, string $source = 'webhook'): ?array
    {
        $shopDomain = strtolower($shopDomain);
        $brixStore = BrixStore::where('shop_domain', $shopDomain)->first();

        if ($brixStore && (int) $brixStore->agency_id !== $agencyId) {
            return null;
        }

        if ($brixStore) {
            $brixStore->update([
                'installation_status' => 'INSTALLED',
                'authorization_status' => 'AUTHORIZED',
                'uninstalled_at' => null,
                'shopify_shop_id' => $shopifyShopId ?? $brixStore->shopify_shop_id,
                'installed_at' => $brixStore->installed_at ?? now(),
                'last_active_at' => now(),
            ]);
        } else {
            $brixStore = BrixStore::create([
                'agency_id' => $agencyId,
                'shop_domain' => $shopDomain,
                'shopify_shop_id' => $shopifyShopId,
                'store_name' => Str::of(Str::before($shopDomain, '.myshopify.com'))->replace('-', ' ')->title()->toString(),
                'status' => 'active',
                'installation_status' => 'INSTALLED',
                'authorization_status' => 'AUTHORIZED',
                'plan' => 'Starter',
                'installed_at' => now(),
                'last_active_at' => now(),
            ]);
        }

        $relationshipStatus = DB::connection('agency')->transaction(function () use ($agencyId, $brixStore) {
            $relationship = AgencyStore::where('agency_id', $agencyId)
                ->where('store_id', $brixStore->id)
                ->lockForUpdate()
                ->first();

            if (! $relationship) {
                $relationship = AgencyStore::create([
                    'agency_id' => $agencyId,
                    'store_id' => $brixStore->id,
                    'relationship_status' => 'PENDING',
                ]);
            } elseif ($relationship->relationship_status === 'DISCONNECTED') {
                $relationship->update(['relationship_status' => 'PENDING', 'disconnected_at' => null]);
            }
            // Already PENDING/AUTHORIZED/ACTIVE — a reinstall must not regress it.

            return $relationship->relationship_status;
        });

        $organisation = Organisation::where('brix_agency_id', $agencyId)->first();
        if ($organisation) {
            LocalStoreSync::sync($organisation, $brixStore->refresh(), $relationshipStatus);
        }

        BrixActivityLog::record('STORE_INSTALLATION_DETECTED', $agencyId, $brixStore->id, [
            'shop_domain' => $shopDomain,
            'source' => $source,
        ]);

        return ['store' => $brixStore->refresh(), 'relationship_status' => $relationshipStatus];
    }
}

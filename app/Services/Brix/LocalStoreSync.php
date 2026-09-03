<?php

namespace App\Services\Brix;

use App\Models\Brix\Store as BrixStore;
use App\Models\Organisation;
use App\Models\Store as LocalStore;
use App\Models\StoreModule;

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
}

<?php

namespace App\Services\Brix;

use App\Models\Partners\AgencyStore;
use App\Models\Partners\AgencyStoreOnboarding;
use App\Models\Store as BrixStore;
use Illuminate\Support\Facades\DB;

/**
 * The AgencyStore.relationship_status transition logic — PENDING ->
 * AUTHORIZED -> ACTIVE — shared between the agency-facing flow
 * (StoreConnectionController, an authenticated agency user clicking
 * through their own dashboard) and the merchant-facing flow
 * (AgencyShopifyWebhookController, a merchant clicking through inside
 * the Shopify-embedded app). Extracted so both paths lock and transition
 * the same row through one code path — whichever side acts first wins,
 * the other is a no-op, and neither can drift out of sync with the
 * other.
 */
class StoreAuthorization
{
    /**
     * @return array{0: string, 1: bool} [relationship_status, justAuthorized]
     */
    public static function authorize(BrixStore $brixStore, int $agencyId): array
    {
        return DB::transaction(function () use ($agencyId, $brixStore) {
            $relationship = AgencyStore::where('agency_id', $agencyId)
                ->where('store_id', $brixStore->id)
                ->lockForUpdate()
                ->first();

            if (in_array($relationship?->relationship_status, ['ACTIVE', 'AUTHORIZED'], true)) {
                return [$relationship->relationship_status, false]; // Idempotent — already at or past this stage.
            }

            if ($relationship) {
                $relationship->update(['relationship_status' => 'AUTHORIZED', 'authorized_at' => now(), 'disconnected_at' => null]);
            } else {
                AgencyStore::create([
                    'agency_id' => $agencyId,
                    'store_id' => $brixStore->id,
                    'relationship_status' => 'AUTHORIZED',
                    'authorized_at' => now(),
                ]);
            }

            return ['AUTHORIZED', true];
        });
    }

    /**
     * @throws \RuntimeException with message 'NOT_AUTHORIZED' if the store
     *                            hasn't been authorized yet.
     */
    public static function activate(BrixStore $brixStore, int $agencyId): bool
    {
        return DB::transaction(function () use ($agencyId, $brixStore) {
            $relationship = AgencyStore::where('agency_id', $agencyId)
                ->where('store_id', $brixStore->id)
                ->lockForUpdate()
                ->first();

            if (! $relationship || ! in_array($relationship->relationship_status, ['AUTHORIZED', 'ACTIVE'], true)) {
                throw new \RuntimeException('NOT_AUTHORIZED');
            }

            if ($relationship->relationship_status === 'ACTIVE') {
                return false; // Idempotent — double-click protection, nothing to log again.
            }

            $relationship->update(['relationship_status' => 'ACTIVE', 'activated_at' => now()]);

            AgencyStoreOnboarding::where('agency_id', $agencyId)
                ->where('shop_domain', $brixStore->shop_domain)
                ->whereNotIn('status', ['COMPLETED', 'FAILED'])
                ->update(['status' => 'COMPLETED', 'created_store_id' => $brixStore->id]);

            return true;
        });
    }
}

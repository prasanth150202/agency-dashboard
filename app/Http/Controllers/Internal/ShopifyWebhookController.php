<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Internal\Concerns\AuthorizesInternalWebhook;
use App\Models\Store;
use App\Models\StoreConnectionAttempt;
use App\Models\StoreModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Server-to-server only, kept for whatever legacy integration might still
 * call it — the documented caller (Cartninja_admin_dashboard's
 * install_shop.php / uninstall_shop.php) is retired; live installs go
 * through Internal\AgencyShopifyWebhookController instead. Authenticated
 * by a shared secret header, not by a logged-in session.
 */
class ShopifyWebhookController extends Controller
{
    use AuthorizesInternalWebhook;

    public function storeConnected(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
            'shopify_shop_id' => ['nullable', 'string', 'max:60'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);

        $attempt = StoreConnectionAttempt::with('organisation')
            ->where('shop_domain', $shopDomain)
            ->whereIn('status', ['STARTED', 'AUTHORIZING', 'INSTALL_REQUIRED', 'INSTALLING'])
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();

        if (! $attempt || ! $attempt->organisation) {
            // No agency is currently connecting this store — nothing to do.
            return response()->json(['success' => true, 'message' => 'No pending connection attempt.']);
        }

        $agencyId = $attempt->organisation->brixAgency()->id;

        $store = Store::updateOrCreate(
            ['shop_domain' => $shopDomain],
            [
                'agency_id' => $agencyId,
                'store_name' => $this->guessStoreName($shopDomain),
                'shopify_shop_id' => $validated['shopify_shop_id'] ?? null,
                'status' => 'active',
                'installation_status' => 'INSTALLED',
                'authorization_status' => 'AUTHORIZED',
                'installed_at' => now(),
                'uninstalled_at' => null,
                'last_active_at' => now(),
            ]
        );

        if ($store->wasRecentlyCreated) {
            $this->attachModules($store);
        }

        $attempt->update(['status' => 'COMPLETED', 'store_id' => $store->id]);

        return response()->json(['success' => true]);
    }

    public function storeUninstalled(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $store = Store::where('shop_domain', strtolower($validated['shop_domain']))->first();

        if ($store) {
            // Status fields only — never delete the row or its Payout/
            // Commission/AgencyLedger history.
            $store->update([
                'installation_status' => 'UNINSTALLED',
                'authorization_status' => 'REVOKED',
                'uninstalled_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * One row per known BRIX module, defaulting to inactive. Only flipped to
     * active where the Cart Ninja app's own feature tables show a real,
     * existing row for this shop — never fabricated.
     */
    private function attachModules(Store $store): void
    {
        $activeModules = [];
        try {
            $activeModules = $this->detectActiveModules($store->shop_domain);
        } catch (\Throwable $e) {
            report($e);
        }

        foreach (StoreModule::MODULES as $key => $label) {
            $store->modules()->create([
                'module_key' => $key,
                'module_name' => $label,
                'is_active' => in_array($key, $activeModules, true),
                'last_updated_at' => now(),
            ]);
        }
    }

    private function detectActiveModules(string $shopDomain): array
    {
        $active = [];
        $conn = DB::connection('cartninja');

        // cart_drawer has no is_active column — a configured row means the
        // merchant has set it up at all, which is the closest real signal.
        if ($conn->getSchemaBuilder()->hasTable('cart_drawer')
            && $conn->table('cart_drawer')->where('shop_domain', $shopDomain)->exists()) {
            $active[] = 'cart_drawer';
        }
        if ($conn->getSchemaBuilder()->hasTable('coupons')
            && $conn->table('coupons')->where('shop_domain', $shopDomain)->where('is_active', 1)->exists()) {
            $active[] = 'coupon';
        }
        if ($conn->getSchemaBuilder()->hasTable('fbt_widget')
            && $conn->table('fbt_widget')->where('shop_domain', $shopDomain)->where('is_active', 1)->exists()) {
            $active[] = 'fbt';
        }

        return $active;
    }
}

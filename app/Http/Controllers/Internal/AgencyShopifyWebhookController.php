<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Internal\Concerns\AuthorizesInternalWebhook;
use App\Models\Partners\ActivityLog as BrixActivityLog;
use App\Models\Partners\AgencyStore;
use App\Models\Partners\AgencyStoreOnboarding;
use App\Models\Partners\Partner;
use App\Models\Store;
use App\Services\Brix\LocalStoreSync;
use App\Services\Brix\StoreAuthorization;
use App\Services\Brix\StoreInstallationSync;
use App\Services\Referral\ReferralAttribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Server-to-server only. Called by whichever PHP app actually owns the
 * live Shopify OAuth flow (install_shop.php / uninstall_shop.php) over
 * HTTP. Authenticated by the same shared secret header, not a logged-in
 * session — there is no Laravel session for a server-to-server call.
 *
 * This controller NEVER performs Shopify OAuth. It only records what
 * already happened on Shopify's side, matching against a pending
 * agency_store_onboarding row to know which agency the install belongs to.
 */
class AgencyShopifyWebhookController extends Controller
{
    use AuthorizesInternalWebhook;

    public function storeInstalled(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
            'shopify_shop_id' => ['nullable', 'string', 'max:60'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);

        $onboarding = AgencyStoreOnboarding::where('shop_domain', $shopDomain)
            ->whereIn('status', ['STARTED', 'AUTHORIZING', 'INSTALL_REQUIRED', 'INSTALLING'])
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();

        if (! $onboarding) {
            // No agency is currently connecting this store — nothing to
            // attribute the install to. Never fabricate an agency_id (the
            // stores table requires a real one). A referral link the
            // merchant came through may still credit a lead (fail-safe).
            ReferralAttribution::handleInstall($shopDomain, null, $validated['shopify_shop_id'] ?? null);

            return response()->json(['success' => true, 'message' => 'No pending connection attempt.']);
        }

        $agencyId = $onboarding->agency_id;
        $store = Store::where('shop_domain', $shopDomain)->first();

        if ($store && (int) $store->agency_id !== (int) $agencyId) {
            $onboarding->update(['status' => 'FAILED', 'failure_reason' => 'Store already connected to another agency.']);

            BrixActivityLog::record('STORE_CONNECTION_FAILED', $agencyId, $store->id, [
                'shop_domain' => $shopDomain,
                'reason' => 'cross_agency_reinstall',
            ]);

            return response()->json(['success' => true, 'message' => 'Store belongs to another agency; onboarding marked failed.']);
        }

        // installation_status/authorization_status here are Shopify's own
        // install + OAuth state — a completed Shopify app install grants
        // the requested scopes, so both flip together. Distinct from (and
        // never sets) AgencyStore::relationship_status, the agency's own
        // separate authorization of this store. Shared with the wizard's
        // on-demand reconcile path (StoreConnectionController).
        $result = StoreInstallationSync::mirror(
            $shopDomain,
            (int) $agencyId,
            $validated['shopify_shop_id'] ?? null,
            'install_webhook',
        );

        if ($result === null) {
            $onboarding->update(['status' => 'FAILED', 'failure_reason' => 'Store already connected to another agency.']);

            return response()->json(['success' => true, 'message' => 'Store belongs to another agency; onboarding marked failed.']);
        }

        $onboarding->update(['status' => 'AUTHORIZING', 'created_store_id' => $result['store']->id]);

        ReferralAttribution::handleInstall($shopDomain, (int) $agencyId, $validated['shopify_shop_id'] ?? null);

        return response()->json(['success' => true]);
    }

    public function storeUninstalled(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);
        $store = Store::where('shop_domain', $shopDomain)->first();

        if (! $store) {
            return response()->json(['success' => true, 'message' => 'Unknown store; nothing to disconnect.']);
        }

        DB::transaction(function () use ($store) {
            // Status fields only — never delete the row or its ledger/
            // commission/payout/activity-log history.
            $store->update([
                'installation_status' => 'UNINSTALLED',
                'authorization_status' => 'REVOKED',
                'uninstalled_at' => now(),
            ]);

            AgencyStore::where('store_id', $store->id)
                ->whereIn('relationship_status', ['PENDING', 'AUTHORIZED', 'ACTIVE'])
                ->get()
                ->each(fn (AgencyStore $rel) => $rel->update(['relationship_status' => 'DISCONNECTED', 'disconnected_at' => now()]));
        });

        LocalStoreSync::touch($store->refresh(), 'DISCONNECTED');

        ReferralAttribution::syncStore($store);

        BrixActivityLog::record('STORE_DISCONNECTED', $store->agency_id, $store->id, [
            'shop_domain' => $shopDomain,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Merchant self-service counterpart of the agency's own connect-wait
     * wizard: called by the Shopify-embedded Brix app (cartdrawerv2_ui)
     * on behalf of a merchant checking whether their store has a pending
     * agency connection to complete. Read-only — never mutates state.
     */
    public function storeStatus(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);
        $store = Store::where('shop_domain', $shopDomain)->first();

        if (! $store || $store->installation_status !== 'INSTALLED') {
            return response()->json(['success' => true, 'data' => ['stage' => 'NOT_INSTALLED', 'shop_domain' => $shopDomain]]);
        }

        $relationship = AgencyStore::where('agency_id', $store->agency_id)->where('store_id', $store->id)->first();
        $agencyName = Partner::find($store->agency_id)?->name;

        $stage = match ($relationship?->relationship_status) {
            'ACTIVE' => 'COMPLETE',
            'AUTHORIZED' => 'ACTIVATION_REQUIRED',
            default => 'AUTHORIZATION_REQUIRED',
        };

        return response()->json(['success' => true, 'data' => [
            'stage' => $stage,
            'shop_domain' => $shopDomain,
            'agency_name' => $agencyName,
        ]]);
    }

    /**
     * Merchant self-service "Authorize" — same transition as
     * StoreConnectionController::authorize(), triggered by a merchant
     * inside their own Shopify admin instead of an agency user in their
     * dashboard. agency_id is derived only from the shop-domain-matched
     * Store row, never from request input — the caller (cartdrawerv2_ui)
     * only ever supplies the shop_domain it already verified via Shopify's
     * own authenticated session.
     */
    public function storeAuthorize(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);
        $store = Store::where('shop_domain', $shopDomain)->first();

        if (! $store || $store->installation_status !== 'INSTALLED') {
            return response()->json(['success' => false, 'message' => 'BRIX installation is required.'], 422);
        }

        $agencyId = $store->agency_id;

        [$relationshipStatus, $justAuthorized] = StoreAuthorization::authorize($store, $agencyId);

        LocalStoreSync::touch($store, $relationshipStatus);

        ReferralAttribution::syncStore($store);

        if ($justAuthorized) {
            BrixActivityLog::record('STORE_AUTHORIZED', $agencyId, $store->id, [
                'shop_domain' => $shopDomain,
                'initiated_by' => 'merchant_shopify_admin',
            ]);
        }

        return response()->json(['success' => true, 'data' => ['relationship_status' => $relationshipStatus]]);
    }

    /**
     * Merchant self-service "Activate" — same transition as
     * StoreConnectionController::activate(). On success, returns a
     * signed, time-limited, unauthenticated redirect URL the merchant's
     * browser can be sent to directly from inside the Shopify admin
     * iframe (they have no Agency Dashboard login).
     */
    public function storeActivate(Request $request): JsonResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $shopDomain = strtolower($validated['shop_domain']);
        $store = Store::where('shop_domain', $shopDomain)->first();

        if (! $store || $store->installation_status !== 'INSTALLED') {
            return response()->json(['success' => false, 'message' => 'BRIX installation could not be verified.'], 422);
        }

        $agencyId = $store->agency_id;

        try {
            $justActivated = StoreAuthorization::activate($store, $agencyId);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => 'Store must be authorized before activation.'], 409);
        }

        LocalStoreSync::touch($store, 'ACTIVE');

        ReferralAttribution::syncStore($store);

        if ($justActivated) {
            BrixActivityLog::record('STORE_ACTIVATED', $agencyId, $store->id, [
                'shop_domain' => $shopDomain,
                'initiated_by' => 'merchant_shopify_admin',
            ]);
        }

        $redirectUrl = URL::temporarySignedRoute('public.stores.connect.success', now()->addMinutes(60), ['store' => $store->id]);

        return response()->json(['success' => true, 'data' => ['redirect_url' => $redirectUrl]]);
    }
}

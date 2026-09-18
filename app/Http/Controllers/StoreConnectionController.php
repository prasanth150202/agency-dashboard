<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Partners\ActivityLog as BrixActivityLog;
use App\Models\Partners\AgencyStore;
use App\Models\Partners\AgencyStoreOnboarding;
use App\Models\Store;
use App\Services\Brix\BrixInstallCheck;
use App\Services\Brix\LocalStoreSync;
use App\Services\Brix\RateLimitGuard;
use App\Services\Brix\StoreAuthorization;
use App\Services\Brix\StoreInstallationSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Shopify Store Connection / Onboarding flow.
 *
 * Two distinct truths are kept carefully separate throughout this
 * controller (see the module docs in the merged database's schema):
 *  - Shopify installation/authentication — Store::installation_status /
 *    authorization_status, written only by the external Shopify app via
 *    Internal\AgencyShopifyWebhookController. This controller only ever
 *    reads that state; it never performs Shopify OAuth itself.
 *  - Agency authorization — AgencyStore::relationship_status
 *    (PENDING -> AUTHORIZED -> ACTIVE, or -> DISCONNECTED), which is
 *    entirely this controller's responsibility and a real server-side
 *    decision the agency makes about a store that already belongs to it.
 *
 * agency_id is always derived from the authenticated session
 * (Organisation::brixAgency()) — never from request input.
 */
class StoreConnectionController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        // Normalize BEFORE validating the format — a pasted
        // "https://yourstore.myshopify.com" is a very plausible input
        // (copied straight from a browser address bar) and must be
        // accepted, not rejected for carrying a scheme the regex below
        // never expected to see.
        if ($request->filled('shop_domain')) {
            $request->merge(['shop_domain' => $this->normalizeDomain((string) $request->input('shop_domain'))]);
        }

        $validated = $request->validateWithBag('store', [
            'shop_domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i'],
        ], [
            'shop_domain.regex' => 'Enter a valid Shopify store domain, e.g. yourstore.myshopify.com',
        ]);

        $domain = $validated['shop_domain'];

        if (RateLimitGuard::tooMany("connect:shop:{$domain}", 8, 600)
            || RateLimitGuard::tooMany("connect:agency:{$agency->id}", 30, 3600)) {
            return redirect()->route('stores.index')->withErrors(
                ['shop_domain' => 'Too many connection attempts. Please wait a few minutes and try again.'],
                'store'
            );
        }
        RateLimitGuard::hit("connect:shop:{$domain}");
        RateLimitGuard::hit("connect:agency:{$agency->id}");

        $store = Store::where('shop_domain', $domain)->first();

        if ($store && (int) $store->agency_id !== (int) $agency->id) {
            BrixActivityLog::record('STORE_CONNECTION_FAILED', $agency->id, $store->id, [
                'shop_domain' => $domain,
                'reason' => 'cross_agency',
            ], $request);

            return redirect()->route('stores.index')
                ->with('error', 'This store is already connected to another agency.');
        }

        if ($store) {
            $relationship = AgencyStore::where('agency_id', $agency->id)->where('store_id', $store->id)->first();

            if ($relationship?->relationship_status === 'ACTIVE') {
                return redirect()->route('stores.show', $store)->with('info', 'Store is already active.');
            }
        }

        $rawToken = Str::random(64);

        $onboarding = AgencyStoreOnboarding::where('agency_id', $agency->id)
            ->where('shop_domain', $domain)
            ->whereNotIn('status', ['COMPLETED', 'FAILED'])
            ->latest()
            ->first();

        if ($onboarding) {
            $onboarding->update([
                'state_token' => AgencyStoreOnboarding::hashToken($rawToken),
                'status' => 'STARTED',
                'failure_reason' => null,
                'expires_at' => now()->addMinutes(15),
            ]);
        } else {
            $onboarding = AgencyStoreOnboarding::create([
                'agency_id' => $agency->id,
                'state_token' => AgencyStoreOnboarding::hashToken($rawToken),
                'shop_domain' => $domain,
                'status' => 'STARTED',
                'created_store_id' => $store?->id,
                'expires_at' => now()->addMinutes(15),
            ]);
        }

        BrixActivityLog::record('STORE_CONNECTION_STARTED', $agency->id, $store?->id, [
            'shop_domain' => $domain,
            'already_installed' => $store?->installation_status === 'INSTALLED',
        ], $request);

        return redirect()->route('stores.connect.wait', ['token' => $rawToken]);
    }

    public function wait(Request $request, string $token): View
    {
        $onboarding = $this->authorizeToken($request, $token);

        return view('stores.connect-wait', ['token' => $token, 'onboarding' => $onboarding]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $onboarding = AgencyStoreOnboarding::findByRawToken($token);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        if (! $onboarding || (int) $onboarding->agency_id !== (int) $agency->id) {
            return response()->json(['success' => false, 'error' => 'Your session has expired. Please try again.'], 404);
        }

        if ($onboarding->status === 'STARTED' && $onboarding->isExpired()) {
            $onboarding->update(['status' => 'EXPIRED']);
        }

        return response()->json(['success' => true, 'data' => $this->buildStatus($organisation, $onboarding)]);
    }

    /**
     * Browser-navigation endpoint (a real link, opened by a direct click —
     * browsers block window.open() from non-click code). Never talks to
     * Shopify's OAuth endpoints itself; it only decides where to send the
     * merchant, verified against the real stores table.
     */
    public function install(Request $request, string $token): RedirectResponse
    {
        $onboarding = $this->authorizeToken($request, $token);

        if ($onboarding->isExpired()) {
            $onboarding->update(['status' => 'EXPIRED']);
            abort(410, 'This connection attempt expired. Please start again from Stores.');
        }

        $store = $this->reconcileInstallation($onboarding);

        if ($store?->installation_status === 'INSTALLED') {
            // Already installed — never send an already-connected merchant
            // back to the App Store. Send them straight into their
            // installed BRIX app to continue the agency-authorization flow.
            return redirect()->away(Store::brixAppUrlFor($onboarding->shop_domain, '/app/agency-connect'));
        }

        $onboarding->update(['status' => 'INSTALL_REQUIRED']);

        BrixActivityLog::record('STORE_INSTALLATION_REQUIRED', $onboarding->agency_id, $store?->id, [
            'shop_domain' => $onboarding->shop_domain,
        ], $request);

        return redirect()->away(config('services.shopify.app_store_url'));
    }

    /**
     * Abandon a pending onboarding attempt from the Stores index — e.g.
     * the agency gave up waiting for the merchant to install BRIX. Never
     * touches the real stores/agency_stores rows; only marks this
     * onboarding attempt itself FAILED so it drops off the pending list.
     */
    public function cancel(Request $request, AgencyStoreOnboarding $onboarding): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        abort_unless((int) $onboarding->agency_id === (int) $agency->id, 404);

        $onboarding->update(['status' => 'FAILED', 'failure_reason' => 'Cancelled by agency.']);

        BrixActivityLog::record('STORE_CONNECTION_FAILED', $agency->id, $onboarding->created_store_id, [
            'shop_domain' => $onboarding->shop_domain,
            'reason' => 'cancelled_by_agency',
        ], $request);

        return redirect()->route('stores.index')->with('info', 'Connection attempt cancelled.');
    }

    /**
     * "Allow this store to be managed from your agency dashboard." A real
     * server-side operation — not a second Shopify OAuth flow. Store-
     * scoped (not token-scoped) so it also works from the Stores index
     * grid for a store whose onboarding attempt has since expired.
     */
    public function authorize(Request $request, Store $store): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $store);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        if (RateLimitGuard::tooMany("authorize:store:{$store->id}", 10, 60)) {
            return $this->respond($request, false, 'Too many requests. Please slow down.', status: 429, redirectTo: $store);
        }
        RateLimitGuard::hit("authorize:store:{$store->id}");

        if ($store->installation_status !== 'INSTALLED') {
            return $this->respond($request, false, 'BRIX installation is required.', status: 422, redirectTo: $store);
        }

        if ((int) $store->agency_id !== (int) $agency->id) {
            BrixActivityLog::record('STORE_CONNECTION_FAILED', $agency->id, $store->id, [
                'shop_domain' => $store->shop_domain,
                'reason' => 'cross_agency',
            ], $request);

            return $this->respond($request, false, 'Agency authorization failed.', status: 403, redirectTo: $store);
        }

        $alreadyAuthorized = AgencyStore::where('agency_id', $agency->id)
            ->where('store_id', $store->id)
            ->whereIn('relationship_status', ['AUTHORIZED', 'ACTIVE'])
            ->exists();

        if (! $alreadyAuthorized) {
            BrixActivityLog::record('STORE_AUTHORIZATION_STARTED', $agency->id, $store->id, [
                'shop_domain' => $store->shop_domain,
            ], $request);
        }

        [$relationshipStatus, $justAuthorized] = StoreAuthorization::authorize($store, $agency->id);

        LocalStoreSync::touch($store, $relationshipStatus);

        if ($justAuthorized) {
            BrixActivityLog::record('STORE_AUTHORIZED', $agency->id, $store->id, [
                'shop_domain' => $store->shop_domain,
            ], $request);
        }

        return $this->respond(
            $request,
            true,
            $relationshipStatus === 'ACTIVE' ? 'Store is already active.' : 'Store authorized. It can now be activated.',
            ['relationship_status' => $relationshipStatus, 'store_id' => $store->id],
            redirectTo: $store
        );
    }

    public function activate(Request $request, Store $store): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $store);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        if (RateLimitGuard::tooMany("activate:store:{$store->id}", 10, 60)) {
            return $this->respond($request, false, 'Too many requests. Please slow down.', status: 429, redirectTo: $store);
        }
        RateLimitGuard::hit("activate:store:{$store->id}");

        if ($store->installation_status !== 'INSTALLED') {
            return $this->respond($request, false, 'BRIX installation could not be verified.', status: 422, redirectTo: $store);
        }

        if ((int) $store->agency_id !== (int) $agency->id) {
            return $this->respond($request, false, 'Store activation failed.', status: 403, redirectTo: $store);
        }

        try {
            $justActivated = StoreAuthorization::activate($store, $agency->id);
        } catch (\RuntimeException $e) {
            return $this->respond($request, false, 'Store must be authorized before activation.', status: 409, redirectTo: $store);
        }

        LocalStoreSync::touch($store, 'ACTIVE');

        if ($justActivated) {
            BrixActivityLog::record('STORE_ACTIVATED', $agency->id, $store->id, [
                'shop_domain' => $store->shop_domain,
            ], $request);
        }

        return $this->respond($request, true, 'Store activated.', ['redirect' => route('stores.show', $store)], redirectTo: $store);
    }

    /**
     * Available for a DISCONNECTED store on the Stores index. Never
     * creates a duplicate stores/agency_stores row — always reuses the
     * existing ones for this shop_domain.
     */
    public function reconnect(Request $request, Store $store): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $store);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        if (RateLimitGuard::tooMany("reconnect:store:{$store->id}", 5, 600)) {
            return $this->respond($request, false, 'Too many requests. Please slow down.', status: 429, redirectTo: $store);
        }
        RateLimitGuard::hit("reconnect:store:{$store->id}");

        if ((int) $store->agency_id !== (int) $agency->id) {
            return $this->respond($request, false, 'Access denied.', status: 403, redirectTo: $store);
        }

        BrixActivityLog::record('STORE_RECONNECTED', $agency->id, $store->id, [
            'shop_domain' => $store->shop_domain,
            'previously' => 'DISCONNECTED',
        ], $request);

        if ($store->installation_status === 'INSTALLED') {
            // Shopify install survived — only the agency relationship was
            // disconnected. Reset it to PENDING so the merchant/agency has
            // to explicitly re-authorize (never auto-reactivate).
            AgencyStore::updateOrCreate(
                ['agency_id' => $agency->id, 'store_id' => $store->id],
                ['relationship_status' => 'PENDING', 'disconnected_at' => null]
            );

            LocalStoreSync::touch($store, 'PENDING');

            return $this->respond($request, true, 'Store reconnected. You can now authorize it again.', ['redirect' => route('stores.show', $store)], redirectTo: $store);
        }

        // Truly uninstalled — needs a fresh trip through the Shopify App Store.
        $rawToken = Str::random(64);

        AgencyStoreOnboarding::create([
            'agency_id' => $agency->id,
            'state_token' => AgencyStoreOnboarding::hashToken($rawToken),
            'shop_domain' => $store->shop_domain,
            'status' => 'STARTED',
            'created_store_id' => $store->id,
            'expires_at' => now()->addMinutes(15),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => ['redirect' => route('stores.connect.wait', ['token' => $rawToken])]]);
        }

        return redirect()->route('stores.connect.wait', ['token' => $rawToken]);
    }

    /**
     * The real stores.installation_status only flips to INSTALLED when a
     * Shopify install webhook lands during a live onboarding. A store the
     * agency connects that was ALREADY installed — before this
     * onboarding, or straight from the App Store — never triggers that,
     * which is what leaves the merchant bounced back to the App Store for
     * an app they already have.
     *
     * Backstop: when the row isn't INSTALLED, ask the live BRIX backend
     * directly (cached briefly — the wait screen polls buildStatus() every
     * couple of seconds) and, if it confirms the install, record it now —
     * the same effect as the webhook — so the rest of the flow proceeds
     * straight to agency authorization. A missing/unreachable backend
     * leaves the row untouched, so the existing App-Store fallback still
     * applies.
     */
    private function reconcileInstallation(AgencyStoreOnboarding $onboarding): ?Store
    {
        $store = Store::where('shop_domain', $onboarding->shop_domain)->first();

        if ($store?->installation_status === 'INSTALLED') {
            return $store;
        }

        // Cache a string sentinel, not the bool/null itself — Cache::remember
        // never stores a null return and would re-hit a down backend on
        // every 2.5s poll. "unknown" and "not_installed" are both cached.
        $verdict = Cache::remember(
            "brix-install-check:{$onboarding->shop_domain}",
            now()->addSeconds(30),
            fn () => match (BrixInstallCheck::isInstalled($onboarding->shop_domain)) {
                true => 'installed',
                false => 'not_installed',
                null => 'unknown',
            },
        );

        if ($verdict !== 'installed') {
            return $store;
        }

        $result = StoreInstallationSync::mirror($onboarding->shop_domain, (int) $onboarding->agency_id, null, 'wizard_reconcile');

        if ($result === null) {
            // Shop belongs to another agency — leave the flow to fail the
            // same way it would have without this backstop.
            return $store;
        }

        if (in_array($onboarding->status, ['STARTED', 'INSTALL_REQUIRED', 'INSTALLING'], true)) {
            $onboarding->update(['status' => 'AUTHORIZING', 'created_store_id' => $result['store']->id]);
        }

        Cache::forget("brix-install-check:{$onboarding->shop_domain}");

        return $result['store'];
    }

    private function authorizeToken(Request $request, string $token): AgencyStoreOnboarding
    {
        $onboarding = AgencyStoreOnboarding::findByRawToken($token);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        abort_unless($onboarding && (int) $onboarding->agency_id === (int) $agency->id, 404);

        return $onboarding;
    }

    /**
     * Normalized status for the wait screen's poll: never exposes
     * Shopify tokens, secrets, or raw session data — only the state
     * machine's current stage plus enough to render steps 1-5.
     */
    private function buildStatus(Organisation $organisation, AgencyStoreOnboarding $onboarding): array
    {
        $agency = $organisation->brixAgency();

        if ($onboarding->status === 'EXPIRED') {
            return ['stage' => 'EXPIRED', 'shop_domain' => $onboarding->shop_domain, 'message' => 'This connection attempt expired. Please try again.'];
        }

        if ($onboarding->status === 'FAILED') {
            return ['stage' => 'FAILED', 'shop_domain' => $onboarding->shop_domain, 'message' => $onboarding->failure_reason ?? "We couldn't connect this store."];
        }

        $store = $this->reconcileInstallation($onboarding);

        if (! $store || $store->installation_status === 'NOT_INSTALLED') {
            return ['stage' => 'INSTALL_REQUIRED', 'shop_domain' => $onboarding->shop_domain, 'agency_name' => $agency->name, 'message' => "Click the button below — we'll pick up automatically once it's installed."];
        }

        if ($store->installation_status === 'UNINSTALLED') {
            return ['stage' => 'UNINSTALLED', 'shop_domain' => $onboarding->shop_domain, 'message' => 'BRIX is no longer installed on this store.'];
        }

        if ($store->installation_status !== 'INSTALLED') {
            return ['stage' => 'INSTALLING', 'shop_domain' => $onboarding->shop_domain, 'message' => 'Installing BRIX…'];
        }

        // Installed. From here, everything is agency-authorization state —
        // self-heal so the store-scoped authorize/activate routes below
        // have something correct to act on even if the install webhook
        // hasn't reached this app yet for some reason.
        $relationship = AgencyStore::where('agency_id', $agency->id)->where('store_id', $store->id)->first();
        LocalStoreSync::touch($store, $relationship?->relationship_status ?? 'PENDING');

        return match ($relationship?->relationship_status) {
            'ACTIVE' => [
                'stage' => 'COMPLETE',
                'shop_domain' => $onboarding->shop_domain,
                'message' => 'Store connected successfully.',
                'redirect' => route('stores.show', $store),
            ],
            'AUTHORIZED' => [
                'stage' => 'ACTIVATION_REQUIRED',
                'shop_domain' => $onboarding->shop_domain,
                'agency_name' => $agency->name,
                'message' => 'Click the button below to finish connecting this store.',
                'store_id' => $store->id,
                'activate_url' => route('stores.activate', $store),
                'brix_app_url' => Store::brixAppUrlFor($onboarding->shop_domain, '/app/agency-connect'),
            ],
            default => [
                'stage' => 'AUTHORIZATION_REQUIRED',
                'shop_domain' => $onboarding->shop_domain,
                'agency_name' => $agency->name,
                'message' => "Click the button below to continue — we won't proceed without your confirmation.",
                'store_id' => $store->id,
                'authorize_url' => route('stores.authorize', $store),
                'brix_app_url' => Store::brixAppUrlFor($onboarding->shop_domain, '/app/agency-connect'),
            ],
        };
    }

    /**
     * The wizard page calls these endpoints via fetch (Accept: application/
     * json) and wants JSON back; the Stores index grid uses plain HTML
     * forms for the same endpoints and wants a normal redirect with a
     * flashed message. Both share one implementation so the security
     * checks above never diverge between the two entry points.
     */
    private function respond(Request $request, bool $success, string $message, array $data = [], int $status = 200, ?Store $redirectTo = null): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            $payload = ['success' => $success, 'message' => $message];
            if ($data) {
                $payload['data'] = $data;
            }

            return response()->json($payload, $status);
        }

        $target = $redirectTo ? redirect()->route('stores.show', $redirectTo) : redirect()->route('stores.index');

        return $success ? $target->with('success', $message) : $target->with('error', $message);
    }

    private function normalizeDomain(string $domain): string
    {
        return Str::of($domain)
            ->trim()
            ->lower()
            ->replaceMatches('/^[a-z]+:\/\//', '') // strip any scheme, incl. javascript:/data:/file:
            ->trim('/')
            ->toString();
    }
}

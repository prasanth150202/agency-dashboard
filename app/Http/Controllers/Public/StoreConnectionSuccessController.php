<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The only unauthenticated page in this app that's specific to a single
 * store. Reached by a merchant's browser being redirected here (breaking
 * out of Shopify's admin iframe) right after they self-activate their
 * store's agency connection from inside the Shopify-embedded Brix app —
 * they have no Agency Dashboard login, so this must never require one.
 *
 * Protected by Laravel's signed-URL mechanism (see the `signed` +
 * `throttle` middleware on this route) rather than authentication:
 * the URL itself is the credential, generated only by
 * AgencyShopifyWebhookController::storeActivate() after a real
 * activation succeeds, and expires after a short window.
 */
class StoreConnectionSuccessController extends Controller
{
    public function show(Request $request, Store $store): View
    {
        $agencyName = $store->organisation?->brixAgency()->name ?? 'your agency';

        return view('public.store-connect-success', [
            'store' => $store,
            'agencyName' => $agencyName,
        ]);
    }
}

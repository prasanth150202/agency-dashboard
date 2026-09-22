<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Referral\ReferralClick;
use App\Services\Referral\ReferralAttribution;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * The store-domain step of a referral link. BRIX's install callback only
 * ever reports a shop_domain, so this is where the merchant tells us which
 * store they are about to install on — binding it to their referral click
 * server-side — before continuing to the existing Shopify App Store
 * listing. Reached only through the signed, expiring URL that
 * ReferralRedirectController hands out.
 */
class ReferralStoreController extends Controller
{
    public function show(Request $request, string $ref): View
    {
        $this->resolveClick($ref);

        return view('public.referral-store');
    }

    public function submit(Request $request, string $ref): RedirectResponse
    {
        $click = $this->resolveClick($ref);

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        if (! ReferralAttribution::bindShop($click, $validated['shop_domain'])) {
            return back()
                ->withInput()
                ->withErrors(['shop_domain' => 'Enter a valid Shopify store domain, e.g. yourstore.myshopify.com']);
        }

        return redirect()->away($click->trackingLink->destination_url);
    }

    private function resolveClick(string $ref): ReferralClick
    {
        try {
            $clickId = (int) Crypt::decryptString($ref);
        } catch (DecryptException) {
            abort(404);
        }

        $click = ReferralClick::with('trackingLink')->find($clickId);

        abort_unless($click && ReferralAttribution::isClickUsable($click), 404);

        return $click;
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Public, unauthenticated landing point for a referral link
 * (/ref/BRIX-82KD). Records the click, then hands the merchant a signed,
 * expiring link to the store-domain step (ReferralStoreController), which
 * in turn continues to the system-controlled App Store destination —
 * never a URL supplied by the agency. No lead is created here: that only
 * happens once BRIX's install callback confirms a genuinely new shop.
 */
class ReferralRedirectController extends Controller
{
    public function redirect(Request $request, string $code): RedirectResponse
    {
        $trackingLink = TrackingLink::where('code', $code)
            ->where('status', TrackingLink::STATUS_ACTIVE)
            ->first();

        if (! $trackingLink) {
            abort(404);
        }

        // A DB hiccup while logging the click must never stop the
        // merchant reaching the App Store — same fail-open convention as
        // RateLimitGuard/ActivityLog elsewhere in this app.
        try {
            $click = ReferralClick::create([
                'agency_id' => $trackingLink->agency_id,
                'tracking_link_id' => $trackingLink->id,
                'referral_code' => $trackingLink->code,
                'session_id' => $request->session()->getId(),
                'referrer' => $request->header('referer'),
                // Only the exact value "qr" is ever recorded — anything else
                // is ignored, so the query string can't inject arbitrary labels.
                'source' => $request->query('src') === ReferralClick::SOURCE_QR ? ReferralClick::SOURCE_QR : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->away($trackingLink->destination_url);
        }

        $manualLead = Lead::where('tracking_link_id', $trackingLink->id)
            ->where('source', Lead::SOURCE_MANUAL)
            ->whereNotNull('shop_domain')
            ->first();

        if ($manualLead) {
            $click->update(['shop_domain' => $manualLead->shop_domain]);

            return redirect()->away($trackingLink->destination_url);
        }

        // The click id travels only as an encrypted value inside a signed,
        // expiring URL — no browser-controlled agency/store/link id.
        return redirect()->to(URL::temporarySignedRoute(
            'public.referral.store',
            now()->addMinutes((int) config('services.referrals.capture_minutes', 30)),
            ['ref' => Crypt::encryptString((string) $click->id)]
        ));
    }
}

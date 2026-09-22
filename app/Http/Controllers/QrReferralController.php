<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Services\Referral\ReferralQr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** QR Referrals: a printable QR for each of the agency's existing referral links. */
class QrReferralController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agencyId = $organisation->brixAgency()->id;

        $links = TrackingLink::where('agency_id', $agencyId)
            ->orderByDesc('created_at')
            ->get();

        $scans = ReferralClick::where('agency_id', $agencyId)
            ->where('source', ReferralClick::SOURCE_QR)
            ->selectRaw('tracking_link_id, COUNT(*) as scans')
            ->groupBy('tracking_link_id')
            ->pluck('scans', 'tracking_link_id');

        return view('qr.index', [
            'links' => $links,
            'scans' => $scans,
            'qrSvg' => $links->mapWithKeys(fn (TrackingLink $l) => [$l->id => ReferralQr::svg($l, 220)]),
        ]);
    }

    public function download(TrackingLink $trackingLink): Response
    {
        Gate::authorize('view', $trackingLink);

        return response(ReferralQr::svg($trackingLink, 1024), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($trackingLink->code).'-qr.svg"',
        ]);
    }
}

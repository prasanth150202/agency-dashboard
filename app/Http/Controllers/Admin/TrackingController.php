<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use Illuminate\Http\Request;

/** Super Admin's global click-to-active funnel, optionally filtered by agency. */
class TrackingController extends Controller
{
    private const RANGES = [7, 30, 90];

    public function index(Request $request)
    {
        $range = (int) $request->query('range', 30);
        $range = in_array($range, self::RANGES, true) ? $range : 30;
        $from = now()->subDays($range - 1)->startOfDay();
        $agencyId = $request->filled('agency') ? (int) $request->query('agency') : null;

        $clicks = ReferralClick::query()->where('created_at', '>=', $from)->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId));
        $leads = Lead::query()->where('first_clicked_at', '>=', $from)->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId));

        $totals = [
            'clicks' => (clone $clicks)->count(),
            'leads' => (clone $leads)->count(),
            'installed' => (clone $leads)->whereNotNull('installed_at')->count(),
            'active' => (clone $leads)->whereNotNull('activated_at')->count(),
        ];

        $byAgency = ReferralClick::query()
            ->join('agencies', 'agencies.id', '=', 'referral_clicks.agency_id')
            ->where('referral_clicks.created_at', '>=', $from)
            ->when($agencyId, fn ($q) => $q->where('referral_clicks.agency_id', $agencyId))
            ->selectRaw('referral_clicks.agency_id, agencies.name as agency_name, COUNT(*) as clicks')
            ->groupBy('referral_clicks.agency_id', 'agencies.name')
            ->orderByDesc('clicks')
            ->get();

        return view('admin.tracking.index', [
            'range' => $range,
            'ranges' => self::RANGES,
            'agencyId' => $agencyId,
            'agencies' => Partner::orderBy('name')->get(['id', 'name']),
            'totals' => $totals,
            'byAgency' => $byAgency,
        ]);
    }
}

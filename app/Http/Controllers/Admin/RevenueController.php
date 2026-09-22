<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use Illuminate\Http\Request;

/**
 * Super Admin's global Revenue view — the same `referral_revenue_events`
 * table the Agency Dashboard reads (see RevenueController), with no agency
 * scope by default; an agency filter narrows it. Never the legacy
 * `transactions` table.
 */
class RevenueController extends Controller
{
    private const RANGES = ['30' => 30, '90' => 90, '365' => 365, 'all' => null];

    public function index(Request $request)
    {
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '90';
        $days = self::RANGES[$range];
        $from = $days ? now()->subDays($days - 1)->startOfDay() : null;
        $type = in_array($request->query('type'), ReferralRevenueEvent::TYPES, true) ? $request->query('type') : null;
        $agencyId = $request->filled('agency') ? (int) $request->query('agency') : null;

        $events = ReferralRevenueEvent::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($type, fn ($q) => $q->where('revenue_type', $type))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId));

        $byCurrency = fn ($q, string $column) => (clone $q)
            ->selectRaw("currency, SUM({$column}) as total")->groupBy('currency')->pluck('total', 'currency');

        $byAgency = ReferralRevenueEvent::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($type, fn ($q) => $q->where('revenue_type', $type))
            ->join('agencies', 'agencies.id', '=', 'referral_revenue_events.agency_id')
            ->selectRaw('referral_revenue_events.agency_id, agencies.name as agency_name, referral_revenue_events.currency, SUM(referral_revenue_events.revenue_amount) as total')
            ->groupBy('referral_revenue_events.agency_id', 'agencies.name', 'referral_revenue_events.currency')
            ->orderByDesc('total')
            ->get()
            ->groupBy('agency_id');

        return view('admin.revenue.index', [
            'range' => $range,
            'ranges' => array_keys(self::RANGES),
            'type' => $type,
            'types' => ReferralRevenueEvent::TYPES,
            'agencyId' => $agencyId,
            'agencies' => Partner::orderBy('name')->get(['id', 'name']),
            'totalRevenue' => $byCurrency($events, 'revenue_amount'),
            'byType' => (clone $events)->selectRaw('revenue_type, currency, SUM(revenue_amount) as total')
                ->groupBy('revenue_type', 'currency')->get()->groupBy('revenue_type'),
            'byAgency' => $byAgency,
            'eventCount' => (clone $events)->count(),
            'rows' => (clone $events)->with(['lead.trackingLink', 'agency'])
                ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }
}

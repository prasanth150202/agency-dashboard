<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use Illuminate\Http\Request;

/** Super Admin's global view of every agency's referral links. */
class ReferralLinkController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'agency', 'status', 'channel']);

        $links = TrackingLink::query()
            ->with('agency')
            ->withCount(['clicks', 'leads'])
            ->withCount(['leads as active_stores_count' => fn ($q) => $q->where('lead_stage', Lead::STAGE_ACTIVE)])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->channel($filters['channel'] ?? null)
            ->when(! empty($filters['agency']), fn ($q) => $q->where('agency_id', (int) $filters['agency']))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $linkIds = $links->getCollection()->pluck('id');

        $revenue = ReferralRevenueEvent::query()
            ->join('leads', 'leads.id', '=', 'referral_revenue_events.lead_id')
            ->whereIn('leads.tracking_link_id', $linkIds)
            ->selectRaw('leads.tracking_link_id as link_id, referral_revenue_events.currency, SUM(referral_revenue_events.revenue_amount) as total')
            ->groupBy('leads.tracking_link_id', 'referral_revenue_events.currency')
            ->get()->groupBy('link_id');

        $commission = ReferralCommission::query()
            ->whereIn('tracking_link_id', $linkIds)
            ->whereIn('status', ReferralCommission::EARNED_STATUSES)
            ->selectRaw('tracking_link_id, currency, SUM(commission_amount) as total')
            ->groupBy('tracking_link_id', 'currency')
            ->get()->groupBy('tracking_link_id');

        return view('admin.referral-links.index', [
            'links' => $links,
            'filters' => $filters,
            'channels' => TrackingLink::CHANNELS,
            'agencies' => Partner::orderBy('name')->get(['id', 'name']),
            'revenue' => $revenue,
            'commission' => $commission,
        ]);
    }
}

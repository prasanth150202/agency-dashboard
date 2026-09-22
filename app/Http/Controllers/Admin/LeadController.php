<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Super Admin's global Leads view — the exact same `leads` table the Agency
 * Dashboard reads, with no per-agency scope. Never trusts an agency id from
 * the request beyond using it as a plain filter value.
 */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'agency', 'stage', 'brix_status', 'link', 'channel', 'campaign', 'from', 'to']);

        $leads = Lead::query()
            ->with(['trackingLink', 'store', 'agency'])
            ->search($filters['search'] ?? null)
            ->stage($filters['stage'] ?? null)
            ->when(! empty($filters['agency']), fn ($q) => $q->where('agency_id', (int) $filters['agency']))
            ->when(! empty($filters['brix_status']), fn ($q) => $q->where('brix_status', $filters['brix_status']))
            ->when(! empty($filters['link']), fn ($q) => $q->where('tracking_link_id', (int) $filters['link']))
            ->when(in_array($filters['channel'] ?? null, TrackingLink::CHANNELS, true),
                fn ($q) => $q->whereHas('trackingLink', fn ($l) => $l->where('channel', $filters['channel'])))
            ->when(! empty($filters['campaign']), fn ($q) => $q->whereHas('trackingLink', fn ($l) => $l->where('campaign_name', $filters['campaign'])))
            ->when(! empty($filters['from']), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(! empty($filters['to']), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $leadIds = $leads->getCollection()->pluck('id');

        $revenue = ReferralRevenueEvent::query()->whereIn('lead_id', $leadIds)
            ->selectRaw('lead_id, currency, SUM(revenue_amount) as total')->groupBy('lead_id', 'currency')->get()
            ->groupBy('lead_id')->map(fn ($rows) => $rows->pluck('total', 'currency'));

        $commission = ReferralCommission::query()->whereIn('lead_id', $leadIds)->whereIn('status', ReferralCommission::EARNED_STATUSES)
            ->selectRaw('lead_id, currency, SUM(commission_amount) as total, MAX(status) as any_status')->groupBy('lead_id', 'currency')->get()
            ->groupBy('lead_id')->map(fn ($rows) => $rows->pluck('total', 'currency'));

        $commissionStatus = $this->commissionStatusByLead($leadIds);

        return view('admin.leads.index', [
            'leads' => $leads,
            'filters' => $filters,
            'stages' => Lead::STAGES,
            'channels' => TrackingLink::CHANNELS,
            'agencies' => Partner::orderBy('name')->get(['id', 'name']),
            'links' => TrackingLink::orderBy('name')->get(['id', 'name', 'agency_id']),
            'revenue' => $revenue,
            'commission' => $commission,
            'commissionStatus' => $commissionStatus,
        ]);
    }

    public function show(Lead $lead)
    {
        $lead->load(['trackingLink', 'store', 'agency', 'creator', 'events' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')]);

        return view('admin.leads.show', [
            'lead' => $lead,
            'revenueEvents' => ReferralRevenueEvent::where('lead_id', $lead->id)->orderByDesc('occurred_at')->limit(50)->get(),
            'commissions' => ReferralCommission::where('lead_id', $lead->id)->with('payouts')->orderByDesc('created_at')->limit(50)->get(),
        ]);
    }

    /** @param  \Illuminate\Support\Collection<int,int>  $leadIds  @return array<int,string> */
    private function commissionStatusByLead($leadIds): array
    {
        // Highest-priority status per lead: paid > in_payout > eligible > pending.
        $rank = ['paid' => 4, 'in_payout' => 3, 'eligible' => 2, 'pending' => 1];

        return ReferralCommission::whereIn('lead_id', $leadIds)
            ->get(['lead_id', 'status'])
            ->groupBy('lead_id')
            ->map(function ($rows) use ($rank) {
                return $rows->sortByDesc(fn ($r) => $rank[$r->status] ?? 0)->first()->status;
            })
            ->all();
    }
}

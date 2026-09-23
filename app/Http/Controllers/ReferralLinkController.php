<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Partners\ActivityLog;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Services\Analytics\AgencyAnalytics;
use App\Services\Analytics\TrendChart;
use App\Services\Referral\Commission\CommissionSourceMode;
use App\Services\Referral\ReferralReporting;
use App\Support\AnalyticsPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReferralLinkController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        $links = $organisation->trackingLinks()
            ->withCount(['clicks', 'leads'])
            ->withCount(['leads as installed_count' => fn ($q) => $q->whereNotNull('installed_at')])
            ->withCount(['leads as active_stores_count' => fn ($q) => $q->where('lead_stage', Lead::STAGE_ACTIVE)])
            ->withMax('clicks as last_click_at', 'created_at')
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->channel($request->query('channel'))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // Revenue and commission come only from verified referral revenue
        // events and the commissions derived from them — never the legacy
        // `transactions` table, always scoped to this agency, and kept per
        // currency (never added across currencies).
        $linkIds = $links->getCollection()->pluck('id');
        $reporting = new ReferralReporting($agency->id);
        $revenue = $reporting->revenueByLink($linkIds);
        $commission = $reporting->commissionByLink($linkIds);

        $links->getCollection()->transform(function (TrackingLink $link) use ($revenue, $commission) {
            $link->revenue = $revenue[$link->id] ?? [];
            $link->commission = $commission[$link->id] ?? [];
            $link->last_activity_at = $link->last_click_at ? \Illuminate\Support\Carbon::parse($link->last_click_at) : $link->updated_at;

            return $link;
        });

        $commissionMode = CommissionSourceMode::forAgency($agency->id);
        return view('referral-links.index', [
            'organisation' => $organisation,
            'links' => $links,
            'filters' => $request->only(['search', 'status', 'channel']),
            'channels' => TrackingLink::CHANNELS,
            'revenueCurrency' => ReferralRevenueEvent::USAGE_CURRENCY,
            'subscriptionNotVerifiable' => CommissionSourceMode::isValid($commissionMode)
                && CommissionSourceMode::includes($commissionMode, ReferralRevenueEvent::TYPE_SUBSCRIPTION),
            'metrics' => [
                'total_links' => TrackingLink::where('agency_id', $agency->id)->count(),
                'active_links' => TrackingLink::where('agency_id', $agency->id)->where('status', TrackingLink::STATUS_ACTIVE)->count(),
                'total_clicks' => $organisation->trackingLinks()->withCount('clicks')->get()->sum('clicks_count'),
                'total_leads' => $agency->leads()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'campaign_name' => ['nullable', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $link = TrackingLink::create([
            'agency_id' => $agency->id,
            'name' => $validated['name'],
            'campaign_name' => $validated['campaign_name'] ?? null,
            'channel' => $validated['channel'],
            'notes' => $validated['notes'] ?? null,
            // System-controlled destination — never taken from the form.
            'destination_url' => config('services.shopify.app_store_url'),
            'status' => TrackingLink::STATUS_ACTIVE,
        ]);

        ActivityLog::record('referral_link.created', $agency->id, null, [
            'tracking_link_id' => $link->id,
            'code' => $link->code,
            'channel' => $link->channel,
        ], $request);

        return back()
            ->with('success', "Referral link \"{$link->name}\" created.")
            ->with('createdLink', [
                'name' => $link->name,
                'code' => $link->code,
                'url' => $link->referral_url,
            ]);
    }

    public function update(Request $request, TrackingLink $trackingLink): RedirectResponse
    {
        Gate::authorize('update', $trackingLink);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'campaign_name' => ['nullable', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $trackingLink->only(['name', 'campaign_name', 'channel', 'notes']);
        $trackingLink->update($validated);

        ActivityLog::record('referral_link.updated', $trackingLink->agency_id, null, [
            'tracking_link_id' => $trackingLink->id,
            'before' => $before,
            'after' => $validated,
            'by' => $request->user()->email,
        ], $request);

        return back()->with('success', "\"{$trackingLink->name}\" updated.");
    }

    public function activate(Request $request, TrackingLink $trackingLink): RedirectResponse
    {
        Gate::authorize('update', $trackingLink);

        $trackingLink->update(['status' => TrackingLink::STATUS_ACTIVE]);

        ActivityLog::record('referral_link.activated', $trackingLink->agency_id, null, [
            'tracking_link_id' => $trackingLink->id,
        ], $request);

        return back()->with('success', "\"{$trackingLink->name}\" is now active.");
    }

    public function deactivate(Request $request, TrackingLink $trackingLink): RedirectResponse
    {
        Gate::authorize('update', $trackingLink);

        $trackingLink->update(['status' => TrackingLink::STATUS_INACTIVE]);

        ActivityLog::record('referral_link.deactivated', $trackingLink->agency_id, null, [
            'tracking_link_id' => $trackingLink->id,
        ], $request);

        return back()->with('success', "\"{$trackingLink->name}\" has been deactivated.");
    }

    /** One link's performance: funnel, trend and recent real activity for the chosen period. */
    public function show(Request $request, TrackingLink $trackingLink)
    {
        Gate::authorize('view', $trackingLink);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $period = AnalyticsPeriod::fromRequest($request, '30d', ['7d', '30d', '3m', '6m', 'ytd', 'all', 'custom']);
        $analytics = new AgencyAnalytics($organisation);
        $reporting = new ReferralReporting((int) $trackingLink->agency_id);

        $recentClicks = ReferralClick::where('tracking_link_id', $trackingLink->id)
            ->latest('created_at')->latest('id')->limit(8)->get(['id', 'shop_domain', 'source', 'created_at']);

        $recentLeads = $trackingLink->leads()->with('store')->latest('created_at')->limit(8)->get();

        return view('referral-links.show', [
            'link' => $trackingLink,
            'period' => $period,
            'funnel' => $analytics->funnel($period, $trackingLink->id),
            'chart' => TrendChart::build($analytics->series($period, $trackingLink->id), ['clicks', 'leads', 'installed']),
            'totals' => [
                'clicks' => $trackingLink->clicks()->count(),
                'qr_scans' => $trackingLink->clicks()->where('source', ReferralClick::SOURCE_QR)->count(),
                'leads' => $trackingLink->leads()->count(),
                'installed' => $trackingLink->leads()->whereNotNull('installed_at')->count(),
                'active' => $trackingLink->leads()->whereNotNull('activated_at')->count(),
                'revenue' => $reporting->revenueByLink([$trackingLink->id])[$trackingLink->id] ?? [],
                'commission' => $reporting->commissionByLink([$trackingLink->id])[$trackingLink->id] ?? [],
            ],
            'recentClicks' => $recentClicks,
            'recentLeads' => $recentLeads,
        ]);
    }

    public function leads(TrackingLink $trackingLink)
    {
        Gate::authorize('view', $trackingLink);

        $leads = $trackingLink->leads()
            ->with('store')
            ->orderByDesc('created_at')
            ->paginate(15);

        // Per-lead financials come only from verified revenue events and
        // their commissions, scoped to this link's own agency.
        $leadIds = $leads->getCollection()->pluck('id');

        $revenue = ReferralRevenueEvent::query()
            ->where('agency_id', $trackingLink->agency_id)
            ->whereIn('lead_id', $leadIds)
            ->selectRaw('lead_id, SUM(revenue_amount) as total')
            ->groupBy('lead_id')
            ->pluck('total', 'lead_id');

        $commission = ReferralCommission::query()
            ->where('agency_id', $trackingLink->agency_id)
            ->whereIn('lead_id', $leadIds)
            ->whereIn('status', ReferralCommission::EARNED_STATUSES)
            ->selectRaw('lead_id, SUM(commission_amount) as total')
            ->groupBy('lead_id')
            ->pluck('total', 'lead_id');

        return view('referral-links.leads', [
            'trackingLink' => $trackingLink,
            'leads' => $leads,
            'revenue' => $revenue,
            'commission' => $commission,
            'revenueCurrency' => ReferralRevenueEvent::USAGE_CURRENCY,
        ]);
    }
}

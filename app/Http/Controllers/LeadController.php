<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Partners\ActivityLog;
use App\Models\Partners\AgencyStore;
use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Brix\BrixInstallCheck;
use App\Services\Referral\ReferralAttribution;
use App\Services\Referral\ReferralReporting;
use App\Services\Shopify\ShopifyStoreResolver;
use App\Support\AnalyticsPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The agency-wide Leads page. Reads only the referral tables; the tenant is
 * always the session-derived organisation, never a request value.
 */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        $filters = $request->only(['search', 'stage', 'link', 'channel', 'view', 'source', 'reached']);
        $period = AnalyticsPeriod::fromRequest($request, 'all', ['all', '7d', '30d', '3m', '6m', 'ytd', 'custom']);

        // `reached` narrows to leads that got to that step inside the period
        // (the dashboard funnel's drill-down); otherwise the period applies
        // to when the lead was created.
        $reached = in_array($filters['reached'] ?? null, ['installed', 'active'], true) ? $filters['reached'] : null;
        $periodColumn = match ($reached) { 'installed' => 'installed_at', 'active' => 'activated_at', default => 'created_at' };

        $scoped = fn () => Lead::forAgency($agency->id)
            ->when($reached, fn ($q) => $q->whereNotNull($periodColumn))
            ->when($period->from, fn ($q) => $q->where($periodColumn, '>=', $period->from))
            ->when($period->key !== 'all', fn ($q) => $q->where($periodColumn, '<=', $period->to))
            ->sourceFilter($filters['source'] ?? null);

        $leads = $scoped()
            ->with(['trackingLink', 'store'])
            ->search($filters['search'] ?? null)
            ->stage($filters['stage'] ?? null)
            ->view($filters['view'] ?? null)
            ->when(! empty($filters['link']), fn ($q) => $q->where('tracking_link_id', (int) $filters['link']))
            ->when(in_array($filters['channel'] ?? null, TrackingLink::CHANNELS, true),
                fn ($q) => $q->whereHas('trackingLink', fn ($l) => $l->where('channel', $filters['channel'])))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $reporting = new ReferralReporting($agency->id);
        $leadIds = $leads->getCollection()->pluck('id');
        $revenue = $reporting->revenueByLead($leadIds);
        $commission = $reporting->commissionByLead($leadIds);

        // Stage + tab counts follow the period/source filters so the header always matches the list.
        $stageCounts = $scoped()
            ->selectRaw('lead_stage, COUNT(*) as total')
            ->groupBy('lead_stage')
            ->pluck('total', 'lead_stage');

        // Tab counts, in the same real-data groupings as scopeView().
        $base = $scoped();
        $viewCounts = [
            'all' => (clone $base)->count(),
            'in_review' => (clone $base)->whereIn('lead_stage', Lead::VIEW_GROUPS['in_review'])->count(),
            'install_started' => (clone $base)->whereIn('lead_stage', Lead::VIEW_GROUPS['install_started'])->count(),
            'installed' => (clone $base)->whereIn('lead_stage', Lead::VIEW_GROUPS['installed'])->count(),
            'active' => (clone $base)->whereIn('lead_stage', Lead::VIEW_GROUPS['active'])->count(),
            'churned' => (clone $base)->where('brix_status', 'UNINSTALLED')->count(),
            'lost' => (clone $base)->whereIn('lead_stage', Lead::VIEW_GROUPS['lost'])->count(),
        ];

        return view('leads.index', [
            'leads' => $leads,
            'filters' => $filters,
            'period' => $period,
            'reached' => $reached,
            'sources' => Lead::SOURCE_FILTERS,
            'stages' => Lead::STAGES,
            'channels' => TrackingLink::CHANNELS,
            'links' => TrackingLink::where('agency_id', $agency->id)->orderBy('name')->get(['id', 'name']),
            'stageCounts' => $stageCounts,
            'viewCounts' => $viewCounts,
            'totalLeads' => (int) $stageCounts->sum(),
            'revenue' => $revenue,
            'commission' => $commission,
        ]);
    }

    /**
     * An agency's own manually-added lead. The submitted website must be a
     * Shopify store; its *.myshopify.com domain is extracted from it. If
     * BRIX is already installed there the lead is approved straight away;
     * otherwise a dedicated TrackingLink is created that sends the merchant
     * directly to install BRIX on that exact shop — never asking them to
     * type the domain again. agency_id/created_by are always server-side.
     */
    public function store(Request $request, ShopifyStoreResolver $resolver): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:160'],
            'contact_email' => ['required', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'website' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'agency_id' => $agency->id,
            'company_name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'] ?? null,
            'website' => $validated['website'],
            'notes' => $validated['notes'] ?? null,
            'source' => Lead::SOURCE_MANUAL,
            'created_by' => $request->user()->id,
            'lead_stage' => Lead::STAGE_NEW,
        ];

        $shopDomain = $resolver->resolve($validated['website']);

        if ($shopDomain === null) {
            return back()->withInput()->withErrors([
                'website' => 'We couldn\'t detect a Shopify store at this URL. If it is a Shopify store, enter its yourstore.myshopify.com address instead.',
            ]);
        }

        if (Lead::where('shop_domain', $shopDomain)->exists()) {
            return back()->withInput()->withErrors([
                'website' => "A lead for {$shopDomain} already exists — check the leads list.",
            ]);
        }

        [$isInstalled, $existingShop] = $this->installState($shopDomain);
        $createdLink = null;
        $store = null;

        $attributes['shop_domain'] = $shopDomain;

        if ($isInstalled) {
            $attributes['brix_status'] = 'INSTALLED';
            $attributes['brix_plan'] = $existingShop->plan_key ?? $existingShop->plan ?? null;
            $attributes['lead_stage'] = Lead::STAGE_INSTALLED;
            $attributes['installed_at'] = now();

            // Only claim store_id when the local store already belongs to
            // THIS agency — a store owned by another agency must never be
            // attached here (ReferralAttribution::syncStore below already
            // no-ops on a mismatch, but store_id itself must stay unset).
            $store = Store::where('shop_domain', $shopDomain)->first();
            if ($store && (int) $store->agency_id === (int) $agency->id) {
                $attributes['store_id'] = $store->id;
            }
        } else {
            $link = TrackingLink::create([
                'agency_id' => $agency->id,
                'name' => "Lead: {$validated['company_name']}",
                'channel' => 'Website',
                'destination_url' => $this->shopifyInstallUrl($shopDomain),
                'notes' => "Auto-created for manually-added lead \"{$validated['company_name']}\" for {$shopDomain}.",
                'status' => TrackingLink::STATUS_ACTIVE,
            ]);

            $attributes['tracking_link_id'] = $link->id;
            $createdLink = $link;
        }

        $lead = Lead::create($attributes);

        if ($store) {
            ReferralAttribution::syncStore($store);
            $lead->refresh();
        }

        ActivityLog::record('lead.created_manually', $agency->id, null, [
            'lead_id' => $lead->id,
            'company_name' => $lead->company_name,
            'shop_domain' => $shopDomain,
            'already_installed' => $isInstalled,
            'by' => $request->user()->email,
        ], $request);

        $message = $isInstalled
            ? "\"{$lead->company_name}\" approved — BRIX is already installed on {$shopDomain}."
            : "\"{$lead->company_name}\" added — BRIX is not installed yet, so a referral install link was created.";

        $redirect = redirect()->route('leads.show', $lead)->with('success', $message);

        if ($createdLink) {
            $redirect->with('createdLink', [
                'name' => $createdLink->name,
                'code' => $createdLink->code,
                'url' => $createdLink->referral_url,
            ]);
        }

        return $redirect;
    }

    private function shopifyInstallUrl(string $shopDomain): string
    {
        $base = (string) config('services.shopify.app_auth_url', '');

        if ($base === '') {
            $base = (string) config('services.shopify.app_store_url');
        }

        return $base.(Str::contains($base, '?') ? '&' : '?').http_build_query(['shop' => $shopDomain]);
    }

    /**
     * Whether BRIX is installed on the shop, per the read-only cartdrawer
     * (`cartninja`) shops table: a row that isn't status=uninstalled. Only
     * when that DB can't be read does it fall back to the live backend.
     *
     * @return array{0: bool, 1: object|null} [installed, cartdrawer shops row]
     */
    private function installState(string $shopDomain): array
    {
        try {
            $shop = DB::connection('cartninja')->table('shops')->where('shop_domain', $shopDomain)->first();
        } catch (\Throwable $e) {
            report($e);

            return [BrixInstallCheck::isInstalled($shopDomain) === true, null];
        }

        $installed = $shop !== null && ($shop->status ?? null) !== 'uninstalled';

        return [$installed, $shop];
    }

    public function show(Lead $lead)
    {
        Gate::authorize('view', $lead);

        $lead->load(['trackingLink', 'store', 'creator', 'events' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')]);

        $reporting = new ReferralReporting((int) $lead->agency_id);

        $revenueEvents = ReferralRevenueEvent::where('agency_id', $lead->agency_id)
            ->where('lead_id', $lead->id)->orderByDesc('occurred_at')->limit(50)->get();
        $commissions = ReferralCommission::where('agency_id', $lead->agency_id)
            ->where('lead_id', $lead->id)->orderByDesc('created_at')->limit(50)->get();

        // Only the lead's own agency relationship — never another agency's.
        $relationship = $lead->store_id
            ? AgencyStore::where('agency_id', $lead->agency_id)->where('store_id', $lead->store_id)->first()
            : null;

        $viaQr = $lead->tracking_link_id && $lead->shop_domain && ReferralClick::where('tracking_link_id', $lead->tracking_link_id)
            ->where('shop_domain', $lead->shop_domain)->where('source', ReferralClick::SOURCE_QR)->exists();

        return view('leads.show', [
            'lead' => $lead,
            'revenue' => $reporting->revenueByLead([$lead->id])[$lead->id] ?? [],
            'commission' => $reporting->commissionByLead([$lead->id])[$lead->id] ?? [],
            'revenueEvents' => $revenueEvents,
            'commissions' => $commissions,
            'manualStages' => Lead::MANUAL_STAGES,
            'sourceLabel' => $lead->source === Lead::SOURCE_MANUAL ? 'Manual' : ($viaQr ? 'QR (referral link)' : 'Referral link'),
            'authorizedAt' => $relationship?->authorized_at,
            'timeline' => $this->timeline($lead, $relationship, $revenueEvents, $commissions),
        ]);
    }

    /**
     * The lead's history from real rows only, oldest first. A step with no
     * recorded row simply doesn't appear.
     *
     * @return list<array{label: string, detail: ?string, at: \Illuminate\Support\Carbon, tone: string}>
     */
    private function timeline(Lead $lead, ?AgencyStore $relationship, $revenueEvents, $commissions): array
    {
        $items = [['label' => $lead->source === Lead::SOURCE_MANUAL ? 'Lead added' : 'Lead created', 'detail' => $lead->creator?->name ? 'by '.$lead->creator->name : null, 'at' => $lead->created_at, 'tone' => 'neutral']];

        foreach ($lead->events as $event) {
            $items[] = [
                'label' => match ($event->event_type) {
                    LeadEvent::CLICKED => 'Referral link clicked',
                    LeadEvent::INSTALL_STARTED => 'Install started',
                    LeadEvent::INSTALLED => 'BRIX installed',
                    LeadEvent::ACTIVATED => 'Store activated',
                    LeadEvent::CHURNED => 'BRIX uninstalled',
                    LeadEvent::REVENUE_GENERATED => 'Revenue generated',
                    default => ucwords(strtolower(str_replace('_', ' ', $event->event_type))),
                },
                'detail' => null,
                'at' => $event->created_at,
                'tone' => match ($event->event_type) { LeadEvent::ACTIVATED => 'success', LeadEvent::CHURNED => 'danger', default => 'info' },
            ];
        }

        if ($relationship?->authorized_at) {
            $items[] = ['label' => 'Store authorized', 'detail' => null, 'at' => $relationship->authorized_at, 'tone' => 'info'];
        }

        foreach ($revenueEvents as $event) {
            $items[] = ['label' => 'Revenue recorded', 'detail' => \App\Support\Currency::format((float) $event->revenue_amount, $event->currency).' · '.ucfirst($event->revenue_type), 'at' => $event->occurred_at, 'tone' => 'success'];
        }

        foreach ($commissions as $commission) {
            $items[] = ['label' => 'Commission generated', 'detail' => \App\Support\Currency::format((float) $commission->commission_amount, $commission->currency).' · '.ucfirst(str_replace('_', ' ', $commission->effective_status)), 'at' => $commission->created_at, 'tone' => 'success'];
        }

        usort($items, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $items;
    }

    /**
     * An agency's own outreach stage. Refused once BRIX has matched a store
     * or install to the lead — from then on the stage follows BRIX truth.
     */
    public function updateStage(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'lead_stage' => ['required', Rule::in(Lead::MANUAL_STAGES)],
        ]);

        if (! $lead->canChangeStageManually()) {
            return back()->with('error', 'This lead is already matched to a BRIX store, so its stage follows the store and cannot be changed by hand.');
        }

        $before = $lead->lead_stage;
        $updates = ['lead_stage' => $validated['lead_stage']];

        if ($validated['lead_stage'] === Lead::STAGE_CONTACTED) {
            $updates['contacted_at'] = $lead->contacted_at ?? now();
        }

        $lead->update($updates);

        ActivityLog::record('lead.stage_changed', $lead->agency_id, null, [
            'lead_id' => $lead->id,
            'from' => $before,
            'to' => $validated['lead_stage'],
            'by' => $request->user()->email,
        ], $request);

        return back()->with('success', 'Lead stage updated.');
    }
}

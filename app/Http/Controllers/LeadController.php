<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Partners\ActivityLog;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Brix\BrixInstallCheck;
use App\Services\Referral\ReferralAttribution;
use App\Services\Referral\ReferralReporting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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

        $filters = $request->only(['search', 'stage', 'link', 'channel', 'view']);

        $leads = $organisation->leads()
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

        $stageCounts = Lead::forAgency($agency->id)
            ->selectRaw('lead_stage, COUNT(*) as total')
            ->groupBy('lead_stage')
            ->pluck('total', 'lead_stage');

        // Tab counts, in the same real-data groupings as scopeView().
        $base = Lead::forAgency($agency->id);
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
     * An agency's own manually-added lead. Website is mandatory so every
     * lead can be checked against the real BRIX install state: if the
     * shop already exists on the read-only `cartninja` connection it is
     * recorded as already-installed (no referral link needed); otherwise
     * a dedicated, single-lead TrackingLink is created so the agency has
     * something to send the prospect to install BRIX. agency_id and
     * created_by are always derived server-side, never from the request.
     */
    public function store(Request $request): RedirectResponse
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

        $shopDomain = $this->shopDomainFromSubmittedUrl($validated['website']);

        if ($shopDomain === null) {
            return back()->withInput()->withErrors([
                'website' => 'Enter a Shopify store URL or storefront URL, e.g. https://yourstore.com.',
            ]);
        }

        if (Lead::where('shop_domain', $shopDomain)->exists()) {
            return back()->withInput()->withErrors([
                'website' => 'This Shopify store already has a lead — check the leads list.',
            ]);
        }

        $isInstalled = BrixInstallCheck::isInstalled($shopDomain) === true;
        $existingShop = $this->lookupExistingShop($shopDomain);
        $createdLink = null;
        $store = null;

        $attributes['shop_domain'] = $shopDomain;

        if ($isInstalled) {
            $attributes['brix_status'] = 'INSTALLED';
            $attributes['brix_plan'] = $existingShop?->plan_key;
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

    /**
     * Extract the Shopify shop from the submitted website/URL without
     * asking the agency to type it twice.
     */
    private function shopDomainFromSubmittedUrl(string $website): ?string
    {
        $value = trim($website);
        $url = $this->absoluteUrl($value);

        $host = parse_url($url, PHP_URL_HOST);
        $directShop = ReferralAttribution::normalizeShopDomain($host ?: $value);

        if ($directShop !== null) {
            return $directShop;
        }

        return $this->discoverShopifyDomain($url);
    }

    private function absoluteUrl(string $value): string
    {
        return Str::contains($value, '://') ? $value : "https://{$value}";
    }

    private function discoverShopifyDomain(string $url): ?string
    {
        foreach ($this->shopifyDiscoveryUrls($url) as $discoveryUrl) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(6)
                    ->get($discoveryUrl);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $shop = ReferralAttribution::normalizeShopDomain($response->json('myshopify_domain'))
                ?? ReferralAttribution::normalizeShopDomain($response->json('shop.myshopify_domain'))
                ?? $this->extractShopifyDomain((string) $response->body());

            if ($shop !== null) {
                return $shop;
            }
        }

        return null;
    }

    private function shopifyDiscoveryUrls(string $url): array
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? null;

        if ($host === null) {
            return [$url];
        }

        $origin = "{$scheme}://{$host}";

        return array_values(array_unique([
            rtrim($url, '/'),
            "{$origin}/meta.json",
            "{$origin}/?view=meta",
        ]));
    }

    private function extractShopifyDomain(string $content): ?string
    {
        if (preg_match('/[a-z0-9][a-z0-9\-]*\.myshopify\.com/i', $content, $matches) !== 1) {
            return null;
        }

        return ReferralAttribution::normalizeShopDomain($matches[0]);
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
     * Best-effort match of a lead's website against the read-only
     * `cartninja.shops` table. Only ever reads that connection — a DB
     * hiccup or an unresolvable (non-myshopify) domain is treated the
     * same as "not found", never as an error the agency has to deal with.
     */
    private function lookupExistingShop(string $shopDomain): ?object
    {
        try {
            return DB::connection('cartninja')->table('shops')->where('shop_domain', $shopDomain)->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function show(Lead $lead)
    {
        Gate::authorize('view', $lead);

        $lead->load(['trackingLink', 'store', 'creator', 'events' => fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id')]);

        $reporting = new ReferralReporting((int) $lead->agency_id);

        return view('leads.show', [
            'lead' => $lead,
            'revenue' => $reporting->revenueByLead([$lead->id])[$lead->id] ?? [],
            'commission' => $reporting->commissionByLead([$lead->id])[$lead->id] ?? [],
            'revenueEvents' => ReferralRevenueEvent::where('agency_id', $lead->agency_id)
                ->where('lead_id', $lead->id)->orderByDesc('occurred_at')->limit(50)->get(),
            'commissions' => ReferralCommission::where('agency_id', $lead->agency_id)
                ->where('lead_id', $lead->id)->orderByDesc('created_at')->limit(50)->get(),
            'manualStages' => Lead::MANUAL_STAGES,
        ]);
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

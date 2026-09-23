<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Store;
use App\Services\Brix\BrixInstallCheck;
use App\Services\Brix\CustomDomainCheck;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{

    /**
     * Every real installed shop, straight from cartninja (the BRIX
     * Shopify app's own DB — the only place plan/trial/subscription
     * status actually live; this app's own `stores` table only gets a
     * row once an agency has onboarded a shop, so it's far sparser).
     * Each row is enriched with which partner it belongs to here, when
     * one exists — never invented, a plain shop is shown with no partner.
     *
     * Filtered/paginated in PHP, not SQL: only 64 rows total, and the
     * dev-store check above (a live, cached, per-shop HTTP probe) isn't
     * something SQL can do at all.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $devFilter = $request->query('dev', ''); // '' | 'real' | 'dev'
        $page = (int) $request->query('page', 1);
        $perPage = 25;

        $allShops = DB::connection('cartninja')->table('shops')
            ->when($search !== '', fn ($q) => $q->where('shop_domain', 'like', '%'.$search.'%'))
            ->orderByDesc('created_at')
            ->get();

        $totalInstalled = $allShops->count();

        // Explicitly false (confirmed stayed on *.myshopify.com), never
        // null (couldn't check) — an unreachable storefront must never
        // get labelled "dev" on absence of evidence.
        $devByDomain = $allShops->mapWithKeys(
            fn ($s) => [strtolower($s->shop_domain) => CustomDomainCheck::hasCustomDomain($s->shop_domain) === false]
        );

        $filtered = $allShops->filter(function ($shop) use ($devFilter, $devByDomain) {
            $isDev = $devByDomain->get(strtolower($shop->shop_domain), false);

            return match ($devFilter) {
                'dev' => $isDev,
                'real' => ! $isDev,
                default => true,
            };
        })->values();

        $domains = $filtered->pluck('shop_domain');

        // Partner attribution: a local Store row is the real, matched
        // relationship (authorized onboarding); a Lead alone (no Store
        // yet) is the next best signal. Neither existing means no
        // partner is attached to this shop — shown as such, not guessed.
        $localStores = Store::whereIn('shop_domain', $domains)->get()->keyBy(fn ($s) => strtolower($s->shop_domain));
        $leads = Lead::whereIn('shop_domain', $domains)->get()->keyBy(fn ($l) => strtolower($l->shop_domain));

        $agencyIds = $localStores->pluck('agency_id')->merge($leads->pluck('agency_id'))->filter()->unique();
        $partners = Partner::whereIn('id', $agencyIds)->get()->keyBy('id');

        $rows = $filtered->map(function ($shop) use ($localStores, $leads, $partners, $devByDomain) {
            $domain = strtolower($shop->shop_domain);
            $localStore = $localStores->get($domain);
            $agencyId = $localStore?->agency_id ?? $leads->get($domain)?->agency_id;

            return (object) [
                'shop_domain' => $shop->shop_domain,
                'partner' => $agencyId ? ($partners->get($agencyId)?->name ?? "Agency #{$agencyId}") : null,
                'plan_key' => $shop->plan_key,
                'plan_label' => Store::PLAN_LABELS[$shop->plan_key] ?? ($shop->plan_name ?: ucfirst((string) $shop->plan_key)),
                'subscription_status' => strtoupper((string) $shop->subscription_status),
                'trial_ends_on' => $shop->trial_ends_on,
                'is_trial' => $shop->trial_ends_on !== null && \Illuminate\Support\Carbon::parse($shop->trial_ends_on)->isFuture(),
                'installed_at' => $shop->created_at,
                'local_store' => $localStore,
                'is_dev' => $devByDomain->get($domain, false),
            ];
        });

        $shops = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.stores.index', [
            'shops' => $shops,
            'rows' => $shops->getCollection(),
            'filters' => $request->only(['search', 'dev']),
            'totalInstalled' => $totalInstalled,
            'devCount' => $devByDomain->filter()->count(),
        ]);
    }

    /**
     * Re-checks a store's real Shopify install state against the live
     * BRIX backend (the same check the partner-side connect wizard
     * uses) — for when brix_superadmin's own installation_status looks
     * stale.
     */
    public function recheck(Store $store)
    {
        $installed = BrixInstallCheck::isInstalled($store->shop_domain);

        if ($installed === null) {
            return back()->with('error', 'Could not reach the live BRIX backend to verify this store.');
        }

        return back()->with(
            $installed ? 'success' : 'info',
            $installed
                ? 'Live check confirms BRIX is installed on this store.'
                : 'Live check confirms BRIX is NOT installed on this store — local status may be stale.'
        );
    }
}

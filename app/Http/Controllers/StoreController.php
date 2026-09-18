<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Partners\AgencyStoreOnboarding;
use App\Models\Store;
use App\Models\StoreModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $sort = $request->query('sort', 'recent');
        $sortMap = [
            'recent' => ['last_active_at', 'desc'],
            'name' => ['store_name', 'asc'],
            'modules' => ['store_name', 'asc'], // refined after fetch, kept simple at query level
        ];
        [$sortColumn, $sortDirection] = $sortMap[$sort] ?? $sortMap['recent'];

        $stores = $organisation->stores()
            ->with('modules')
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->module($request->query('module'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(9)
            ->withQueryString();

        // Onboarding attempts still waiting on Shopify installation (no
        // store row yet, so nothing to show in the grid below). Wrapped
        // defensively — a transient DB hiccup must never break the
        // Stores page itself.
        $pendingConnections = collect();
        try {
            $agency = $organisation->brixAgency();
            $localDomains = $organisation->stores()->pluck('shop_domain');

            $pendingConnections = AgencyStoreOnboarding::where('agency_id', $agency->id)
                ->whereNotIn('status', ['COMPLETED', 'FAILED', 'EXPIRED'])
                ->where('expires_at', '>=', now())
                ->whereNotIn('shop_domain', $localDomains)
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('stores.index', [
            'organisation' => $organisation,
            'stores' => $stores,
            'modules' => StoreModule::MODULES,
            'filters' => $request->only(['search', 'status', 'module', 'sort']),
            'pendingConnections' => $pendingConnections,
        ]);
    }

    public function show(Store $store)
    {
        Gate::authorize('view', $store);

        $store->load('modules');

        $modules = collect(StoreModule::MODULES)->map(function (string $label, string $key) use ($store) {
            $module = $store->modules->firstWhere('module_key', $key);

            return (object) [
                'key' => $key,
                'label' => $label,
                'status' => $module?->status ?? 'inactive',
                'last_updated_at' => $module->last_updated_at ?? null,
            ];
        })->values();

        return view('stores.show', [
            'store' => $store,
            'modules' => $modules,
        ]);
    }

}

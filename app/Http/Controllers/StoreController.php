<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Store;
use App\Models\StoreModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $sort = $request->query('sort', 'recent');
        $sortMap = [
            'recent' => ['last_active_at', 'desc'],
            'name' => ['name', 'asc'],
            'modules' => ['name', 'asc'], // refined after fetch, kept simple at query level
        ];
        [$sortColumn, $sortDirection] = $sortMap[$sort] ?? $sortMap['recent'];

        $stores = Store::query()
            ->where('organisation_id', $organisation->id)
            ->with('modules')
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->module($request->query('module'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(9)
            ->withQueryString();

        return view('stores.index', [
            'organisation' => $organisation,
            'stores' => $stores,
            'modules' => StoreModule::MODULES,
            'filters' => $request->only(['search', 'status', 'module', 'sort']),
        ]);
    }

    public function show(Store $store)
    {
        Gate::authorize('view', $store);

        $store->load('modules');

        $modules = collect(StoreModule::MODULES)->map(function (string $label, string $key) use ($store) {
            $module = $store->modules->firstWhere('module', $key);

            return (object) [
                'key' => $key,
                'label' => $label,
                'status' => $module->status ?? 'inactive',
                'last_updated_at' => $module->last_updated_at ?? null,
            ];
        })->values();

        return view('stores.show', [
            'store' => $store,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $validated = $request->validateWithBag('store', [
            'name' => ['required', 'string', 'max:255'],
            'shop_domain' => ['required', 'string', 'max:255', 'unique:stores,shop_domain'],
            'admin_url' => ['nullable', 'url', 'max:255'],
        ]);

        $domain = Str::of($validated['shop_domain'])
            ->lower()
            ->trim()
            ->replaceMatches('/^https?:\/\//', '')
            ->trim('/')
            ->toString();

        $store = Store::create([
            'organisation_id' => $organisation->id,
            'name' => $validated['name'],
            'shop_domain' => $domain,
            'admin_url' => $validated['admin_url'] ?: "https://{$domain}/admin",
            'status' => 'active',
            'installed_at' => now(),
            'last_active_at' => now(),
        ]);

        foreach (array_keys(StoreModule::MODULES) as $module) {
            $store->modules()->create([
                'module' => $module,
                'status' => 'inactive',
                'last_updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('stores.show', $store)
            ->with('success', "{$store->name} was connected to BRIX.");
    }
}

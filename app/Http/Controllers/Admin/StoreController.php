<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Brix\BrixInstallCheck;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::query()
            ->with('agency')
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->when($request->filled('installation'), fn ($q) => $q->where('installation_status', $request->query('installation')))
            ->orderByDesc('last_active_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.stores.index', [
            'stores' => $stores,
            'filters' => $request->only(['search', 'status', 'installation']),
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

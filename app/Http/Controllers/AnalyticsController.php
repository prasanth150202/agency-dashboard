<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\StoreModule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonthsNoOverflow($i)->startOfMonth());

        $storeGrowth = $months->map(function (Carbon $month) use ($organisation) {
            return $organisation->stores()
                ->where('created_at', '<=', $month->copy()->endOfMonth())
                ->count();
        });

        $revenueByMonth = $months->map(function (Carbon $month) use ($organisation) {
            return (float) $organisation->payouts()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        });

        $storeHealth = [
            'active' => $organisation->stores()->where('status', 'active')->count(),
            'attention' => $organisation->stores()->where('status', 'attention')->count(),
            'offline' => $organisation->stores()->where('status', 'offline')->count(),
        ];

        $totalStores = array_sum($storeHealth);

        $moduleAdoption = collect(StoreModule::MODULES)->map(function (string $label, string $key) use ($organisation) {
            $count = StoreModule::where('module_key', $key)
                ->where('is_active', true)
                ->whereHas('store', fn ($q) => $q->where('agency_id', $organisation->brix_agency_id))
                ->count();

            return ['label' => $label, 'count' => $count];
        })->sortByDesc('count')->values();

        $storeActivity = $organisation->notifications()
            ->with('store')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('analytics.index', [
            'organisation' => $organisation,
            'chartLabels' => $months->map(fn (Carbon $m) => $m->format('M')),
            'storeGrowth' => $storeGrowth,
            'revenueByMonth' => $revenueByMonth,
            'storeHealth' => $storeHealth,
            'totalStores' => $totalStores,
            'moduleAdoption' => $moduleAdoption,
            'storeActivity' => $storeActivity,
        ]);
    }
}

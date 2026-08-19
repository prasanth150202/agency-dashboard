<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Payout;
use App\Models\Store;
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
            return Store::where('organisation_id', $organisation->id)
                ->where('created_at', '<=', $month->copy()->endOfMonth())
                ->count();
        });

        $revenueByMonth = $months->map(function (Carbon $month) use ($organisation) {
            return (float) Payout::where('organisation_id', $organisation->id)
                ->where('status', 'paid')
                ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        });

        $storeHealth = [
            'active' => Store::where('organisation_id', $organisation->id)->where('status', 'active')->count(),
            'attention' => Store::where('organisation_id', $organisation->id)->where('status', 'attention')->count(),
            'offline' => Store::where('organisation_id', $organisation->id)->where('status', 'offline')->count(),
        ];

        $totalStores = array_sum($storeHealth);

        $moduleAdoption = collect(StoreModule::MODULES)->map(function (string $label, string $key) use ($organisation) {
            $count = StoreModule::where('module', $key)
                ->where('status', 'active')
                ->whereHas('store', fn ($q) => $q->where('organisation_id', $organisation->id))
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

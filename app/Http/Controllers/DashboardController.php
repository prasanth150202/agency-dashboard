<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Payout;
use App\Models\Store;
use App\Models\StoreModule;
use App\Support\Metrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $range = (int) $request->query('range', 7);
        $range = in_array($range, [7, 30, 90], true) ? $range : 7;

        $storesQuery = Store::where('organisation_id', $organisation->id);

        $totalStores = (clone $storesQuery)->count();
        $activeStores = (clone $storesQuery)->where('status', 'active')->count();

        $cutoff = Carbon::now()->subDays($range);
        $totalStoresPrevious = (clone $storesQuery)->where('created_at', '<=', $cutoff)->count();
        $activeStoresPrevious = (clone $storesQuery)->where('status', 'active')->where('created_at', '<=', $cutoff)->count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $monthlyRevenue = Payout::where('organisation_id', $organisation->id)
            ->where('status', 'paid')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $lastMonthRevenue = Payout::where('organisation_id', $organisation->id)
            ->where('status', 'paid')
            ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        $pendingPayout = Payout::where('organisation_id', $organisation->id)
            ->where('status', 'pending')
            ->sum('amount');

        $periodStart = Carbon::now()->subDays($range * 2);
        $periodEnd = Carbon::now()->subDays($range);

        $pendingPayoutPreviousPeriod = Payout::where('organisation_id', $organisation->id)
            ->where('status', 'pending')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('amount');

        $metrics = [
            'total_stores' => [
                'value' => $totalStores,
                'delta' => Metrics::percentChange($totalStores, $totalStoresPrevious),
                'caption' => "vs {$range} days ago",
            ],
            'active_stores' => [
                'value' => $activeStores,
                'delta' => Metrics::percentChange($activeStores, $activeStoresPrevious),
                'caption' => "vs {$range} days ago",
            ],
            'monthly_revenue' => [
                'value' => (float) $monthlyRevenue,
                'delta' => Metrics::percentChange((float) $monthlyRevenue, (float) $lastMonthRevenue),
                'caption' => 'vs last month',
            ],
            'pending_payout' => [
                'value' => (float) $pendingPayout,
                'delta' => Metrics::percentChange((float) $pendingPayout, (float) $pendingPayoutPreviousPeriod),
                'caption' => "vs previous {$range} days",
            ],
        ];

        $recentStores = Store::where('organisation_id', $organisation->id)
            ->with('modules')
            ->orderByDesc('last_active_at')
            ->limit(5)
            ->get();

        $storeHealth = [
            'active' => (clone $storesQuery)->where('status', 'active')->count(),
            'attention' => (clone $storesQuery)->where('status', 'attention')->count(),
            'offline' => (clone $storesQuery)->where('status', 'offline')->count(),
        ];

        $moduleAdoption = collect(StoreModule::MODULES)->map(function (string $label, string $key) use ($organisation, $totalStores) {
            $count = StoreModule::where('module', $key)
                ->where('status', 'active')
                ->whereHas('store', fn ($q) => $q->where('organisation_id', $organisation->id))
                ->count();

            return [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percentage' => $totalStores > 0 ? round(($count / $totalStores) * 100) : 0,
            ];
        })->sortByDesc('count')->values();

        $recentNotifications = $organisation->notifications()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'organisation' => $organisation,
            'range' => $range,
            'metrics' => $metrics,
            'recentStores' => $recentStores,
            'storeHealth' => $storeHealth,
            'moduleAdoption' => $moduleAdoption,
            'recentNotifications' => $recentNotifications,
        ]);
    }
}

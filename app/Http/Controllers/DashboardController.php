<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Payout;
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

        $storesQuery = $organisation->stores();

        $totalStores = (clone $storesQuery)->count();
        $activeStores = (clone $storesQuery)->where('status', 'active')->count();

        $cutoff = Carbon::now()->subDays($range);
        $totalStoresPrevious = (clone $storesQuery)->where('created_at', '<=', $cutoff)->count();
        $activeStoresPrevious = (clone $storesQuery)->where('status', 'active')->where('created_at', '<=', $cutoff)->count();

        $finance = $organisation->finance();

        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $monthlyRevenue = $finance->thisMonthEarnings();

        $lastMonthRevenue = $organisation->commissions()
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('agency_commission');

        $pendingPayout = $finance->pendingPayouts() + $finance->processingPayouts();

        $periodStart = Carbon::now()->subDays($range * 2);
        $periodEnd = Carbon::now()->subDays($range);

        $pendingPayoutPreviousPeriod = $organisation->payouts()
            ->whereIn('status', Payout::RESERVING_STATUSES)
            ->whereBetween('requested_at', [$periodStart, $periodEnd])
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

        $recentStores = $organisation->stores()
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
            $count = StoreModule::where('module_key', $key)
                ->where('is_active', true)
                ->whereHas('store', fn ($q) => $q->where('agency_id', $organisation->brix_agency_id))
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
            'financeSummary' => [
                'this_month' => $monthlyRevenue,
                'available' => $finance->availableBalance(),
                'pending' => $pendingPayout,
                'last_payout' => $finance->lastPayout(),
            ],
        ]);
    }
}

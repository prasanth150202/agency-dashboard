<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\StoreModule;
use App\Services\Analytics\AgencyAnalytics;
use App\Services\Analytics\TrendChart;
use App\Services\Finance\UnifiedCommissionService;
use App\Services\Referral\Gamification;
use App\Support\AnalyticsPeriod;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $period = AnalyticsPeriod::fromRequest($request, '30d');
        $analytics = new AgencyAnalytics($organisation);

        $storesQuery = $organisation->stores();
        $totalStores = (clone $storesQuery)->count();

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

        $weekday = $analytics->weekdayActivity($period);
        $gamification = new Gamification((int) $organisation->brix_agency_id);

        return view('dashboard.index', [
            'organisation' => $organisation,
            'period' => $period,
            'kpis' => $analytics->kpis($period),
            'funnel' => $analytics->funnel($period),
            'chart' => TrendChart::build($analytics->series($period), ['revenue', 'commission', 'leads', 'installed'], (new UnifiedCommissionService($organisation))->currency()),
            'activity' => $analytics->activity($period, 10),
            'weekday' => $weekday,
            'weekdayMax' => max($weekday) ?: 0,
            'hasLinks' => $organisation->trackingLinks()->exists(),
            'recentStores' => $organisation->stores()->with('modules')->orderByDesc('last_active_at')->limit(5)->get(),
            'storeHealth' => [
                'active' => (clone $storesQuery)->where('status', 'active')->count(),
                'attention' => (clone $storesQuery)->where('status', 'attention')->count(),
                'offline' => (clone $storesQuery)->where('status', 'offline')->count(),
            ],
            'moduleAdoption' => $moduleAdoption,
            'milestones' => $gamification->milestones(),
            'milestoneProgress' => $gamification->progress(),
        ]);
    }
}

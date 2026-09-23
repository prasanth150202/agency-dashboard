<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Referral\ReferralClick;
use App\Services\Analytics\AgencyAnalytics;
use App\Services\Analytics\TrendChart;
use App\Services\Referral\ReferralFunnel;
use App\Support\AnalyticsPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Referral Tracking: the visual click-to-active funnel, trend and per-link performance. */
class TrackingController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agencyId = $organisation->brixAgency()->id;

        $period = AnalyticsPeriod::fromRequest($request, '30d');
        $analytics = new AgencyAnalytics($organisation);
        $links = $analytics->linkPerformance($period);

        return view('tracking.index', [
            'period' => $period,
            'funnel' => $analytics->funnel($period),
            'chart' => TrendChart::build($analytics->series($period), ['clicks', 'leads', 'installed']),
            'links' => $links,
            'channels' => $this->byChannel($links),
            'recentClicks' => ReferralClick::where('agency_id', $agencyId)
                ->when($period->from, fn ($q) => $q->where('created_at', '>=', $period->from))
                ->where('created_at', '<=', $period->to)
                ->with('trackingLink:id,name,code')
                ->latest('created_at')->latest('id')->limit(10)->get(),
            'hasAnyLinks' => $links->isNotEmpty(),
        ]);
    }

    /** @param  Collection<int, array<string, mixed>>  $links */
    private function byChannel(Collection $links): Collection
    {
        return $links->groupBy(fn (array $row) => $row['link']->channel)
            ->map(fn (Collection $rows) => [
                'clicks' => $rows->sum('clicks'),
                'leads' => $rows->sum('leads'),
                'installed' => $rows->sum('installed'),
                'active' => $rows->sum('active'),
                'install_rate' => ReferralFunnel::rate($rows->sum('installed'), $rows->sum('leads')),
            ])
            ->sortByDesc('clicks');
    }
}

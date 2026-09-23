<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Services\Analytics\AgencyAnalytics;
use App\Services\Analytics\TrendChart;
use App\Services\Referral\Commission\CommissionSourceMode;
use App\Services\Referral\ReferralReporting;
use App\Support\AnalyticsPeriod;
use App\Support\DecimalMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Revenue: verified BRIX billing earned by referred stores. Reads only
 * `referral_revenue_events` (and the commissions derived from them), never
 * the legacy `transactions` table.
 */
class RevenueController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agencyId = $organisation->brixAgency()->id;

        $period = AnalyticsPeriod::fromRequest($request, '3m', ['7d', '30d', '3m', '6m', '12m', 'ytd', 'all', 'custom']);
        $type = in_array($request->query('type'), ReferralRevenueEvent::TYPES, true) ? $request->query('type') : null;

        // A link filter only means something inside this agency: a foreign id
        // matches no lead of ours, so it can only ever yield an empty result.
        $linkId = $request->filled('link') ? (int) $request->query('link') : null;

        $reporting = new ReferralReporting($agencyId);
        $events = $reporting->eventsQuery($period->from, $period->to, $type, $linkId);
        $revenue = $reporting->revenueFor($events);

        $previous = $period->previous();
        $previousRevenue = $previous ? $reporting->revenueFor($reporting->eventsQuery($previous->from, $previous->to, $type, $linkId)) : null;

        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i)->startOfMonth());

        $mode = CommissionSourceMode::forAgency($agencyId);

        return view('revenue.index', [
            'period' => $period,
            'type' => $type,
            'types' => ReferralRevenueEvent::TYPES,
            'linkId' => $linkId,
            'links' => TrackingLink::where('agency_id', $agencyId)->orderBy('name')->get(['id', 'name']),
            'revenue' => $revenue,
            'commission' => $reporting->commissionFor($events),
            'previousRevenue' => $previousRevenue,
            'growth' => $previousRevenue === null ? null : AgencyAnalytics::moneyChange($revenue, $previousRevenue),
            'eventCount' => (clone $events)->count(),
            'storeCount' => (clone $events)->distinct()->count('referral_revenue_events.store_id'),
            'chart' => TrendChart::build($this->series($period, $events), ['revenue', 'commission']),
            'byType' => $this->breakdown(clone $events, 'referral_revenue_events.revenue_type'),
            'byStore' => $this->breakdown(clone $events, 'referral_revenue_events.shop_domain', 8),
            'byLink' => $this->byLink(clone $events),
            'rows' => (clone $events)->with(['lead.trackingLink', 'commission'])
                ->orderByDesc('referral_revenue_events.occurred_at')->orderByDesc('referral_revenue_events.id')
                ->paginate(20)->withQueryString(),
            'monthly' => $months->map(function ($month) use ($reporting, $type, $linkId) {
                $q = $reporting->eventsQuery($month, $month->copy()->endOfMonth(), $type, $linkId);

                return ['label' => $month->format('M Y'), 'revenue' => $reporting->revenueFor($q), 'commission' => $reporting->commissionFor($q)];
            }),
            'subscriptionNotVerifiable' => CommissionSourceMode::isValid($mode)
                && CommissionSourceMode::includes($mode, ReferralRevenueEvent::TYPE_SUBSCRIPTION),
        ]);
    }

    /** Revenue by occurred date, and each event's own commission on that same date. */
    private function series(AnalyticsPeriod $period, $events): array
    {
        $earliest = $period->from ? null : ((clone $events)->min('referral_revenue_events.occurred_at') ?? null);
        $buckets = $period->buckets($earliest ? Carbon::parse($earliest) : null);

        $fold = function ($rows) {
            $out = [];
            foreach ($rows as $row) {
                $out[(string) $row->d][strtoupper((string) $row->c)] = DecimalMoney::toCents($row->t ?? 0);
            }

            return $out;
        };

        $revenue = $fold((clone $events)
            ->selectRaw('DATE(referral_revenue_events.occurred_at) as d, referral_revenue_events.currency as c, SUM(referral_revenue_events.revenue_amount) as t')
            ->groupBy('d', 'c')->get());

        $commission = $fold(ReferralCommission::query()
            ->join('referral_revenue_events as e', 'e.id', '=', 'referral_commissions.revenue_event_id')
            ->whereIn('referral_commissions.status', ReferralCommission::EARNED_STATUSES)
            ->whereIn('e.id', (clone $events)->select('referral_revenue_events.id'))
            ->selectRaw('DATE(e.occurred_at) as d, referral_commissions.currency as c, SUM(referral_commissions.commission_amount) as t')
            ->groupBy('d', 'c')->get());

        $currencies = collect([$revenue, $commission])->flatMap(fn ($days) => collect($days)->flatMap(fn ($c) => array_keys($c)))
            ->unique()->sort()->values()->all();

        return [
            'buckets' => AgencyAnalytics::bucketRows($buckets, [], ['revenue' => $revenue, 'commission' => $commission]),
            'currencies' => $currencies,
        ];
    }

    /** @return list<array{label: string, revenue: array<string, int>, count: int}> */
    private function breakdown($events, string $column, ?int $limit = null): array
    {
        $rows = $events->selectRaw("{$column} as k, referral_revenue_events.currency as c, SUM(referral_revenue_events.revenue_amount) as t, COUNT(*) as n")
            ->groupBy('k', 'c')->get();

        return $rows->groupBy('k')
            ->map(fn ($group, $key) => [
                'label' => (string) $key,
                'revenue' => $group->mapWithKeys(fn ($r) => [strtoupper((string) $r->c) => DecimalMoney::toCents($r->t ?? 0)])->all(),
                'count' => (int) $group->sum('n'),
            ])
            ->sortByDesc(fn ($r) => array_sum($r['revenue']))
            ->when($limit, fn ($c) => $c->take($limit))
            ->values()->all();
    }

    private function byLink($events): array
    {
        $rows = $events->join('leads', 'leads.id', '=', 'referral_revenue_events.lead_id')
            ->leftJoin('tracking_links', 'tracking_links.id', '=', 'leads.tracking_link_id')
            ->selectRaw("COALESCE(tracking_links.name, 'Manual / no link') as k, referral_revenue_events.currency as c, SUM(referral_revenue_events.revenue_amount) as t, COUNT(*) as n")
            ->groupBy('k', 'c')->get();

        return $rows->groupBy('k')
            ->map(fn ($group, $key) => [
                'label' => (string) $key,
                'revenue' => $group->mapWithKeys(fn ($r) => [strtoupper((string) $r->c) => DecimalMoney::toCents($r->t ?? 0)])->all(),
                'count' => (int) $group->sum('n'),
            ])
            ->sortByDesc(fn ($r) => array_sum($r['revenue']))
            ->take(8)->values()->all();
    }
}

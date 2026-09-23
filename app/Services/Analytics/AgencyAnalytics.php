<?php

namespace App\Services\Analytics;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Partners\ActivityLog;
use App\Models\Payout;
use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Services\Finance\UnifiedCommissionService;
use App\Support\AnalyticsPeriod;
use App\Support\DecimalMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only analytics for one agency, built only from real rows: leads,
 * referral_clicks, stores, referral_revenue_events, referral_commissions,
 * legacy store commissions (transactions), payouts and lead_events.
 *
 * Every query is pinned to the agency resolved server-side from the
 * session organisation. Money is kept as integer cents per currency and
 * different currencies are never added together. Nothing is estimated:
 * a window with no data yields zeros/empties, and a trend is only given
 * when a real previous-period value exists to compare against.
 */
class AgencyAnalytics
{
    private readonly int $agencyId;

    public function __construct(private readonly Organisation $organisation)
    {
        $this->agencyId = (int) $organisation->brix_agency_id;
    }

    /** @return array<string, array<string, mixed>> */
    public function kpis(AnalyticsPeriod $period): array
    {
        $previous = $period->previous();
        $summary = (new UnifiedCommissionService($this->organisation))->summary();

        $leads = $this->countLeads('created_at', $period);
        $installed = $this->countInstalledStores($period);
        $revenue = $this->revenue($period);
        $commission = $this->commission($period);

        return [
            'leads' => [
                'value' => $leads,
                'change' => $previous ? AnalyticsPeriod::change($leads, $this->countLeads('created_at', $previous)) : null,
                'context' => number_format($this->leadsQuery()->count()).' leads in total',
            ],
            'installed' => [
                'value' => $installed,
                'change' => $previous ? AnalyticsPeriod::change($installed, $this->countInstalledStores($previous)) : null,
                'context' => number_format($this->organisation->stores()->where('installation_status', 'INSTALLED')->count()).' installed now',
            ],
            'active' => [
                // A current snapshot: there is no status history to trend against.
                'value' => $this->organisation->stores()->where('status', 'active')->count(),
                'change' => null,
                'context' => 'currently active',
            ],
            'revenue' => [
                'value' => $revenue,
                'change' => $previous ? self::moneyChange($revenue, $this->revenue($previous)) : null,
                'context' => 'verified referral revenue',
            ],
            'commission' => [
                'value' => $commission,
                'change' => $previous ? self::moneyChange($commission, $this->commission($previous)) : null,
                'context' => 'earned in period',
            ],
            'available' => [
                'value' => $summary['available'] ?? [],
                'change' => null,
                'context' => 'ready to request',
            ],
            'pending_payout' => [
                'value' => $this->pendingPayouts(),
                'change' => null,
                'context' => 'requested, not yet paid',
            ],
        ];
    }

    /**
     * Click → lead → installed → active. Clicks are counted by when they
     * happened; leads/installed/active by when each lead reached that step,
     * so every stage is a real count inside the window.
     *
     * @return array<string, int>
     */
    public function funnel(AnalyticsPeriod $period, ?int $linkId = null): array
    {
        $clicks = $this->within(ReferralClick::query()->where('agency_id', $this->agencyId), 'created_at', $period)
            ->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId));

        $leads = fn (string $column) => $this->within($this->leadsQuery(), $column, $period)
            ->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId))
            ->count();

        return [
            'clicks' => (clone $clicks)->count(),
            'qr_scans' => (clone $clicks)->where('source', ReferralClick::SOURCE_QR)->count(),
            // The merchant entered their store on the referral step (or it was
            // pre-bound from the lead) and was sent on to install.
            'install_started' => (clone $clicks)->whereNotNull('shop_domain')->count(),
            'leads' => $leads('created_at'),
            'installed' => $leads('installed_at'),
            'active' => $leads('activated_at'),
        ];
    }

    /**
     * One row per chart bucket with every real metric for it.
     *
     * @return array{buckets: list<array<string, mixed>>, currencies: list<string>}
     */
    public function series(AnalyticsPeriod $period, ?int $linkId = null): array
    {
        $earliest = $period->from ? null : $this->earliestActivity();
        $buckets = $period->buckets($earliest);

        $daily = [
            'clicks' => $this->dailyCounts(ReferralClick::query()->where('agency_id', $this->agencyId)
                ->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId)), 'created_at', $period),
            'leads' => $this->dailyCounts($this->leadsQuery()->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId)), 'created_at', $period),
            'installed' => $this->dailyCounts($this->leadsQuery()->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId)), 'installed_at', $period),
        ];

        $revenue = $this->dailyMoney(
            ReferralRevenueEvent::query()->where('agency_id', $this->agencyId)
                ->when($linkId, fn ($q) => $q->whereIn('lead_id', $this->leadsQuery()->where('tracking_link_id', $linkId)->select('id'))),
            'occurred_at', 'revenue_amount', $period
        );

        $commission = $this->dailyMoney(
            ReferralCommission::query()->where('agency_id', $this->agencyId)->whereIn('status', ReferralCommission::EARNED_STATUSES)
                ->when($linkId, fn ($q) => $q->where('tracking_link_id', $linkId)),
            'created_at', 'commission_amount', $period
        );

        if ($linkId === null) {
            $commission = $this->mergeMoney($commission, $this->dailyLegacyCommission($period));
        }

        $currencies = collect([$revenue, $commission])
            ->flatMap(fn (array $days) => collect($days)->flatMap(fn (array $byCurrency) => array_keys($byCurrency)))
            ->unique()->sort()->values()->all();

        $rows = self::bucketRows($buckets, $daily, ['revenue' => $revenue, 'commission' => $commission]);

        return ['buckets' => $rows, 'currencies' => $currencies];
    }

    /**
     * Roll daily values up into chart buckets.
     *
     * @param  list<array<string, mixed>>  $buckets  from AnalyticsPeriod::buckets()
     * @param  array<string, array<string, int>>  $daily  metric => date => count
     * @param  array<string, array<string, array<string, int>>>  $money  metric => date => currency => cents
     * @return list<array<string, mixed>>
     */
    public static function bucketRows(array $buckets, array $daily, array $money): array
    {
        $rows = [];

        foreach ($buckets as $bucket) {
            $row = ['key' => $bucket['key'], 'label' => $bucket['label'], 'clicks' => 0, 'leads' => 0, 'installed' => 0, 'revenue' => [], 'commission' => []];

            foreach (array_keys($money) as $metric) {
                $row[$metric] ??= [];
            }

            for ($day = $bucket['start']->copy()->startOfDay(); $day->lte($bucket['end']); $day->addDay()) {
                $date = $day->toDateString();

                foreach ($daily as $metric => $source) {
                    $row[$metric] ??= 0;
                    $row[$metric] += $source[$date] ?? 0;
                }

                foreach ($money as $metric => $source) {
                    foreach ($source[$date] ?? [] as $currency => $cents) {
                        $row[$metric][$currency] = ($row[$metric][$currency] ?? 0) + $cents;
                    }
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Real events only, newest first, each with a destination when one
     * exists. Never invents an entry.
     *
     * @return Collection<int, array{type: string, title: string, detail: ?string, at: Carbon, url: ?string, tone: string}>
     */
    public function activity(AnalyticsPeriod $period, int $limit = 12): Collection
    {
        $items = collect();

        $leads = $this->within($this->leadsQuery(), 'created_at', $period)
            ->latest('created_at')->limit($limit)->get(['id', 'company_name', 'shop_domain', 'source', 'created_at']);

        foreach ($leads as $lead) {
            $items->push([
                'type' => 'lead', 'tone' => 'info',
                'title' => $lead->source === Lead::SOURCE_MANUAL ? 'Lead added' : 'Referral lead created',
                'detail' => $lead->company_name ?? $lead->shop_domain,
                'at' => $lead->created_at, 'url' => route('leads.show', $lead->id),
            ]);
        }

        $events = $this->within(
            LeadEvent::query()->whereIn('lead_id', $this->leadsQuery()->select('id'))
                ->whereIn('event_type', [LeadEvent::INSTALLED, LeadEvent::ACTIVATED, LeadEvent::CHURNED, LeadEvent::INSTALL_STARTED]),
            'created_at', $period
        )->with('lead:id,company_name,shop_domain')->latest('created_at')->limit($limit)->get();

        foreach ($events as $event) {
            $items->push([
                'type' => 'lead_event',
                'tone' => match ($event->event_type) { LeadEvent::ACTIVATED => 'success', LeadEvent::CHURNED => 'danger', default => 'info' },
                'title' => match ($event->event_type) {
                    LeadEvent::INSTALLED => 'Store installed BRIX',
                    LeadEvent::ACTIVATED => 'Store activated',
                    LeadEvent::CHURNED => 'Store uninstalled BRIX',
                    default => 'Install started',
                },
                'detail' => $event->lead?->company_name ?? $event->lead?->shop_domain,
                'at' => $event->created_at, 'url' => route('leads.show', $event->lead_id),
            ]);
        }

        $revenueEvents = $this->within(ReferralRevenueEvent::query()->where('agency_id', $this->agencyId), 'occurred_at', $period)
            ->with('commission')->latest('occurred_at')->limit($limit)->get();

        foreach ($revenueEvents as $event) {
            $items->push([
                'type' => 'revenue', 'tone' => 'success',
                'title' => 'Revenue recorded',
                'detail' => \App\Support\Currency::format((float) $event->revenue_amount, $event->currency).' from '.$event->shop_domain
                    .($event->commission ? ' · '.\App\Support\Currency::format((float) $event->commission->commission_amount, $event->commission->currency).' commission' : ''),
                'at' => $event->occurred_at, 'url' => $event->lead_id ? route('leads.show', $event->lead_id) : route('revenue.index'),
            ]);
        }

        $payouts = $this->organisation->payouts()->where(function ($q) use ($period) {
            foreach (['requested_at', 'approved_at', 'paid_at', 'rejected_at'] as $column) {
                $q->orWhere(fn ($w) => $this->within($w, $column, $period));
            }
        })->latest('requested_at')->limit($limit)->get();

        foreach ($payouts as $payout) {
            foreach ([
                'requested_at' => ['Payout requested', 'warning'],
                'approved_at' => ['Payout approved', 'info'],
                'paid_at' => ['Payout paid', 'success'],
                'rejected_at' => ['Payout rejected', 'danger'],
            ] as $column => [$title, $tone]) {
                $at = $payout->{$column};

                if ($at && $this->inPeriod($at, $period)) {
                    $items->push([
                        'type' => 'payout', 'tone' => $tone, 'title' => $title,
                        'detail' => trim(($payout->payout_code ?? '').' · '.\App\Support\Currency::format((float) $payout->amount, $payout->currency ?? 'INR'), ' ·'),
                        'at' => $at, 'url' => route('payouts.show', $payout),
                    ]);
                }
            }
        }

        return $items->sortByDesc(fn (array $item) => $item['at']->getTimestamp())->take($limit)->values();
    }

    /**
     * Referral clicks + leads per weekday in the period — only real rows.
     *
     * @return array<string, int> Mon..Sun => count
     */
    public function weekdayActivity(AnalyticsPeriod $period): array
    {
        $week = ['Mon' => 0, 'Tue' => 0, 'Wed' => 0, 'Thu' => 0, 'Fri' => 0, 'Sat' => 0, 'Sun' => 0];

        $sources = [
            $this->dailyCounts(ReferralClick::query()->where('agency_id', $this->agencyId), 'created_at', $period),
            $this->dailyCounts($this->leadsQuery(), 'created_at', $period),
        ];

        foreach ($sources as $days) {
            foreach ($days as $date => $count) {
                $week[Carbon::parse($date)->format('D')] += $count;
            }
        }

        return $week;
    }

    /** @return Collection<int, array<string, mixed>> every agency link, ranked by clicks in the period */
    public function linkPerformance(AnalyticsPeriod $period, ?int $limit = null): Collection
    {
        $clicks = $this->within(ReferralClick::query()->where('agency_id', $this->agencyId), 'created_at', $period)
            ->groupBy('tracking_link_id')->selectRaw('tracking_link_id, COUNT(*) as n')->pluck('n', 'tracking_link_id');

        $stage = fn (string $column) => $this->within($this->leadsQuery()->whereNotNull('tracking_link_id'), $column, $period)
            ->groupBy('tracking_link_id')->selectRaw('tracking_link_id, COUNT(*) as n')->pluck('n', 'tracking_link_id');

        $leads = $stage('created_at');
        $installed = $stage('installed_at');
        $active = $stage('activated_at');

        $revenue = $this->within(ReferralRevenueEvent::query()->where('referral_revenue_events.agency_id', $this->agencyId), 'referral_revenue_events.occurred_at', $period)
            ->join('leads', 'leads.id', '=', 'referral_revenue_events.lead_id')
            ->groupBy('leads.tracking_link_id', 'referral_revenue_events.currency')
            ->selectRaw('leads.tracking_link_id as link_id, referral_revenue_events.currency as currency, SUM(referral_revenue_events.revenue_amount) as total')
            ->get();

        $commission = $this->within(ReferralCommission::query()->where('agency_id', $this->agencyId)->whereIn('status', ReferralCommission::EARNED_STATUSES), 'created_at', $period)
            ->groupBy('tracking_link_id', 'currency')
            ->selectRaw('tracking_link_id as link_id, currency, SUM(commission_amount) as total')
            ->get();

        $fold = function ($rows) {
            $out = [];
            foreach ($rows as $row) {
                $out[(int) $row->link_id][(string) $row->currency] = DecimalMoney::toCents($row->total ?? 0);
            }

            return $out;
        };
        $revenue = $fold($revenue);
        $commission = $fold($commission);

        return TrackingLink::where('agency_id', $this->agencyId)->get()
            ->map(fn (TrackingLink $link) => [
                'link' => $link,
                'clicks' => (int) ($clicks[$link->id] ?? 0),
                'leads' => (int) ($leads[$link->id] ?? 0),
                'installed' => (int) ($installed[$link->id] ?? 0),
                'active' => (int) ($active[$link->id] ?? 0),
                'revenue' => $revenue[$link->id] ?? [],
                'commission' => $commission[$link->id] ?? [],
            ])
            ->sortBy([['clicks', 'desc'], ['leads', 'desc']])
            ->when($limit, fn (Collection $rows) => $rows->take($limit))
            ->values();
    }

    /** @return array<string, int> currency => cents */
    public function revenue(AnalyticsPeriod $period): array
    {
        return $this->sumByCurrency(
            $this->within(ReferralRevenueEvent::query()->where('agency_id', $this->agencyId), 'occurred_at', $period),
            'revenue_amount'
        );
    }

    /** Earned referral commission + legacy store commission in the period. @return array<string, int> */
    public function commission(AnalyticsPeriod $period): array
    {
        $referral = $this->sumByCurrency(
            $this->within(ReferralCommission::query()->where('agency_id', $this->agencyId)->whereIn('status', ReferralCommission::EARNED_STATUSES), 'created_at', $period),
            'commission_amount'
        );

        $legacyCents = DecimalMoney::toCents(round((float) $this->within($this->legacyCommissions(), 'created_at', $period)->sum('agency_commission'), 2));

        if ($legacyCents !== 0) {
            $currency = $this->legacyCurrency();
            $referral[$currency] = ($referral[$currency] ?? 0) + $legacyCents;
        }

        return $referral;
    }

    /** @return array<string, int> */
    private function pendingPayouts(): array
    {
        $rows = $this->organisation->payouts()->whereIn('status', Payout::RESERVING_STATUSES)
            ->groupBy('currency')->selectRaw('currency, SUM(amount) as total')->get();

        $out = [];
        foreach ($rows as $row) {
            $out[strtoupper((string) ($row->currency ?: $this->legacyCurrency()))] = DecimalMoney::toCents($row->total ?? 0);
        }

        return array_filter($out);
    }

    /**
     * A real % change for a single-currency amount; null when currencies
     * differ between windows (they can't be compared) or there is no base.
     *
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     */
    public static function moneyChange(array $current, array $previous): ?float
    {
        $currencies = array_unique(array_merge(array_keys($current), array_keys($previous)));

        if (count($currencies) !== 1) {
            return null;
        }

        $currency = $currencies[0];

        return AnalyticsPeriod::change($current[$currency] ?? 0, $previous[$currency] ?? 0);
    }

    private function leadsQuery()
    {
        return Lead::query()->where('agency_id', $this->agencyId);
    }

    private function legacyCommissions()
    {
        return $this->organisation->commissions()
            ->whereNotIn('commission_status', [Commission::STATUS_REFUNDED, Commission::STATUS_CANCELLED]);
    }

    private function legacyCurrency(): string
    {
        return (new UnifiedCommissionService($this->organisation))->currency();
    }

    private function countLeads(string $column, AnalyticsPeriod $period): int
    {
        return $this->within($this->leadsQuery(), $column, $period)->count();
    }

    private function countInstalledStores(AnalyticsPeriod $period): int
    {
        return $this->within($this->organisation->stores(), 'installed_at', $period)->count();
    }

    private function within($query, string $column, AnalyticsPeriod $period)
    {
        return $query
            ->when($period->from, fn ($q) => $q->where($column, '>=', $period->from))
            ->where($column, '<=', $period->to);
    }

    private function inPeriod(Carbon $at, AnalyticsPeriod $period): bool
    {
        return ($period->from === null || $at->gte($period->from)) && $at->lte($period->to);
    }

    /** @return array<string, int> */
    private function sumByCurrency($query, string $amountColumn): array
    {
        $out = [];

        foreach ($query->groupBy('currency')->selectRaw("currency, SUM({$amountColumn}) as total")->get() as $row) {
            $out[strtoupper((string) $row->currency)] = DecimalMoney::toCents($row->total ?? 0);
        }

        return array_filter($out);
    }

    /** @return array<string, int> date => count */
    private function dailyCounts($query, string $column, AnalyticsPeriod $period): array
    {
        return $this->within($query, $column, $period)
            ->selectRaw("DATE({$column}) as d, COUNT(*) as c")
            ->groupBy('d')
            ->pluck('c', 'd')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** @return array<string, array<string, int>> date => currency => cents */
    private function dailyMoney($query, string $dateColumn, string $amountColumn, AnalyticsPeriod $period): array
    {
        $out = [];

        $rows = $this->within($query, $dateColumn, $period)
            ->selectRaw("DATE({$dateColumn}) as d, currency, SUM({$amountColumn}) as total")
            ->groupBy('d', 'currency')
            ->get();

        foreach ($rows as $row) {
            $out[(string) $row->d][strtoupper((string) $row->currency)] = DecimalMoney::toCents($row->total ?? 0);
        }

        return $out;
    }

    /** @return array<string, array<string, int>> */
    private function dailyLegacyCommission(AnalyticsPeriod $period): array
    {
        $currency = $this->legacyCurrency();

        return $this->within($this->legacyCommissions(), 'created_at', $period)
            ->selectRaw('DATE(created_at) as d, SUM(agency_commission) as total')
            ->groupBy('d')
            ->pluck('total', 'd')
            ->map(fn ($total) => [$currency => DecimalMoney::toCents(round((float) $total, 2))])
            ->filter(fn (array $v) => $v[$currency] !== 0)
            ->all();
    }

    /** @param  array<string, array<string, int>>  ...$sets */
    private function mergeMoney(array ...$sets): array
    {
        $out = [];

        foreach ($sets as $set) {
            foreach ($set as $date => $byCurrency) {
                foreach ($byCurrency as $currency => $cents) {
                    $out[$date][$currency] = ($out[$date][$currency] ?? 0) + $cents;
                }
            }
        }

        return $out;
    }

    private function earliestActivity(): ?Carbon
    {
        $dates = array_filter([
            $this->leadsQuery()->min('created_at'),
            ReferralClick::query()->where('agency_id', $this->agencyId)->min('created_at'),
            ReferralRevenueEvent::query()->where('agency_id', $this->agencyId)->min('occurred_at'),
        ]);

        return $dates ? Carbon::parse(min($dates)) : null;
    }
}

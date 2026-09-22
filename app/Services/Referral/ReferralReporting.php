<?php

namespace App\Services\Referral;

use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Support\Currency;
use App\Support\DecimalMoney;
use Illuminate\Support\Carbon;

/**
 * Read-only aggregation of verified referral revenue and the commissions
 * derived from it, for one agency. Every figure comes from
 * `referral_revenue_events` / `referral_commissions` — never the legacy
 * `transactions` table — and every query is pinned to the agency id the
 * server resolved from the session, never to a request value.
 *
 * Amounts are returned as integer cents grouped by currency:
 * `[groupKey => [currency => cents]]`. Different currencies are never
 * added together.
 */
class ReferralReporting
{
    public function __construct(private readonly int $agencyId) {}

    /** @param  iterable<int>  $leadIds  @return array<int, array<string, int>> */
    public function revenueByLead(iterable $leadIds): array
    {
        return $this->group(
            ReferralRevenueEvent::query()->where('agency_id', $this->agencyId)->whereIn('lead_id', $this->ids($leadIds)),
            'lead_id', 'revenue_amount'
        );
    }

    /** @param  iterable<int>  $leadIds  @return array<int, array<string, int>> */
    public function commissionByLead(iterable $leadIds): array
    {
        return $this->group(
            $this->earnedCommissions()->whereIn('lead_id', $this->ids($leadIds)),
            'lead_id', 'commission_amount'
        );
    }

    /** @param  iterable<int>  $linkIds  @return array<int, array<string, int>> */
    public function revenueByLink(iterable $linkIds): array
    {
        $rows = ReferralRevenueEvent::query()
            ->join('leads', 'leads.id', '=', 'referral_revenue_events.lead_id')
            ->where('referral_revenue_events.agency_id', $this->agencyId)
            ->whereIn('leads.tracking_link_id', $this->ids($linkIds))
            ->groupBy('leads.tracking_link_id', 'referral_revenue_events.currency')
            ->selectRaw('leads.tracking_link_id as group_key, referral_revenue_events.currency as currency, SUM(referral_revenue_events.revenue_amount) as total')
            ->get();

        return $this->fold($rows);
    }

    /** @param  iterable<int>  $linkIds  @return array<int, array<string, int>> */
    public function commissionByLink(iterable $linkIds): array
    {
        return $this->group(
            $this->earnedCommissions()->whereIn('tracking_link_id', $this->ids($linkIds)),
            'tracking_link_id', 'commission_amount'
        );
    }

    /** Agency-wide verified revenue, by currency, in cents. @return array<string, int> */
    public function totalRevenue(): array
    {
        return $this->group(
            ReferralRevenueEvent::query()->where('agency_id', $this->agencyId),
            null, 'revenue_amount'
        )[0] ?? [];
    }

    /** Agency-wide earned commission (pending + eligible + paid), by currency, in cents. @return array<string, int> */
    public function totalCommission(): array
    {
        return $this->group($this->earnedCommissions(), null, 'commission_amount')[0] ?? [];
    }

    /**
     * This agency's revenue events narrowed by period, type and link — the
     * one place those filters are defined, so the revenue figures, the
     * commission figures and the event list can never disagree.
     */
    public function eventsQuery(?Carbon $from = null, ?Carbon $to = null, ?string $type = null, ?int $linkId = null)
    {
        return ReferralRevenueEvent::query()
            ->where('referral_revenue_events.agency_id', $this->agencyId)
            ->when($from, fn ($q) => $q->where('referral_revenue_events.occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('referral_revenue_events.occurred_at', '<=', $to))
            ->when(in_array($type, ReferralRevenueEvent::TYPES, true), fn ($q) => $q->where('referral_revenue_events.revenue_type', $type))
            ->when($linkId, fn ($q) => $q->whereIn(
                'referral_revenue_events.lead_id',
                Lead::query()->where('agency_id', $this->agencyId)->where('tracking_link_id', $linkId)->select('id')
            ));
    }

    /** Revenue for the filtered events, by currency, in cents. @return array<string, int> */
    public function revenueFor($eventsQuery): array
    {
        return $this->group(clone $eventsQuery, null, 'referral_revenue_events.revenue_amount', 'referral_revenue_events.currency')[0] ?? [];
    }

    /** Earned commission on exactly the filtered events, by currency, in cents. @return array<string, int> */
    public function commissionFor($eventsQuery): array
    {
        return $this->group(
            $this->earnedCommissions()->whereIn('revenue_event_id', (clone $eventsQuery)->select('referral_revenue_events.id')),
            null, 'commission_amount'
        )[0] ?? [];
    }

    /** "$10.00", "$10.00 + ₹5.00" for several currencies, or "—" when there is nothing. @param  array<string,int>|null  $byCurrency */
    public static function money(?array $byCurrency): string
    {
        if (! $byCurrency) {
            return '—';
        }

        ksort($byCurrency);

        return collect($byCurrency)
            ->map(fn (int $cents, string $currency) => Currency::format(DecimalMoney::format($cents), $currency))
            ->implode(' + ');
    }

    private function earnedCommissions()
    {
        return ReferralCommission::query()
            ->where('agency_id', $this->agencyId)
            ->whereIn('status', ReferralCommission::EARNED_STATUSES);
    }

    /** @return array<int, array<string, int>> */
    private function group($query, ?string $key, string $amountColumn, string $currencyColumn = 'currency'): array
    {
        $select = ($key ? "{$key} as group_key" : '0 as group_key').", {$currencyColumn} as currency, SUM({$amountColumn}) as total";
        $groupBy = $key ? [$key, $currencyColumn] : [$currencyColumn];

        return $this->fold($query->groupBy($groupBy)->selectRaw($select)->get());
    }

    /** @return array<int, array<string, int>> */
    private function fold($rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->group_key][(string) $row->currency] = DecimalMoney::toCents($row->total ?? 0);
        }

        return $out;
    }

    /** @return list<int> */
    private function ids(iterable $ids): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }
}

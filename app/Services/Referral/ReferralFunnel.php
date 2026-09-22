<?php

namespace App\Services\Referral;

use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Click -> lead -> install -> active -> revenue funnel for one agency,
 * built only from `referral_clicks`, `leads` and `referral_revenue_events`.
 *
 * Clicks are counted by when they happened. Every later step is a cohort:
 * leads whose first click fell inside the period, and how far those leads
 * have got. Nothing here is estimated — an empty period is all zeros.
 */
class ReferralFunnel
{
    public function __construct(private readonly int $agencyId) {}

    /**
     * One row per tracking link (including links with no activity), each
     * with its funnel counts for the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byLink(Carbon $from): Collection
    {
        $clicks = ReferralClick::query()
            ->where('agency_id', $this->agencyId)
            ->where('created_at', '>=', $from)
            ->groupBy('tracking_link_id')
            ->selectRaw('tracking_link_id, COUNT(*) as clicks, SUM(CASE WHEN shop_domain IS NOT NULL THEN 1 ELSE 0 END) as with_store, SUM(CASE WHEN source = "qr" THEN 1 ELSE 0 END) as qr_scans')
            ->get()
            ->keyBy('tracking_link_id');

        $leads = Lead::query()
            ->where('agency_id', $this->agencyId)
            ->where('first_clicked_at', '>=', $from)
            ->groupBy('tracking_link_id')
            ->selectRaw(
                'tracking_link_id, COUNT(*) as leads, '
                .'SUM(CASE WHEN installed_at IS NOT NULL THEN 1 ELSE 0 END) as installed, '
                .'SUM(CASE WHEN activated_at IS NOT NULL THEN 1 ELSE 0 END) as active, '
                .'SUM(CASE WHEN EXISTS (SELECT 1 FROM referral_revenue_events r WHERE r.lead_id = leads.id) THEN 1 ELSE 0 END) as earning'
            )
            ->get()
            ->keyBy('tracking_link_id');

        $links = TrackingLink::where('agency_id', $this->agencyId)->orderBy('name')->get();

        $reporting = new ReferralReporting($this->agencyId);
        $revenue = $reporting->revenueByLink($links->pluck('id'));
        $commission = $reporting->commissionByLink($links->pluck('id'));

        return $links->map(fn (TrackingLink $link) => [
            'link' => $link,
            'clicks' => (int) ($clicks[$link->id]->clicks ?? 0),
            'with_store' => (int) ($clicks[$link->id]->with_store ?? 0),
            'qr_scans' => (int) ($clicks[$link->id]->qr_scans ?? 0),
            'leads' => (int) ($leads[$link->id]->leads ?? 0),
            'installed' => (int) ($leads[$link->id]->installed ?? 0),
            'active' => (int) ($leads[$link->id]->active ?? 0),
            'earning' => (int) ($leads[$link->id]->earning ?? 0),
            'revenue' => $revenue[$link->id] ?? [],
            'commission' => $commission[$link->id] ?? [],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  from byLink()
     * @return array<string, int>
     */
    public static function totals(Collection $rows): array
    {
        return [
            'clicks' => (int) $rows->sum('clicks'),
            'with_store' => (int) $rows->sum('with_store'),
            'qr_scans' => (int) $rows->sum('qr_scans'),
            'leads' => (int) $rows->sum('leads'),
            'installed' => (int) $rows->sum('installed'),
            'active' => (int) $rows->sum('active'),
            'earning' => (int) $rows->sum('earning'),
        ];
    }

    /**
     * The same funnel rolled up by channel.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  from byLink()
     * @return Collection<string, array<string, int>>
     */
    public static function byChannel(Collection $rows): Collection
    {
        return $rows->groupBy(fn (array $row) => $row['link']->channel)
            ->map(fn (Collection $group) => self::totals($group))
            ->sortByDesc('clicks');
    }

    /**
     * Real clicks per calendar day, zero-filled across the period.
     *
     * @return array<string, int> date (Y-m-d) => clicks
     */
    public function dailyClicks(Carbon $from): array
    {
        $counts = ReferralClick::query()
            ->where('agency_id', $this->agencyId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        for ($day = $from->copy()->startOfDay(); $day->lte(now()); $day->addDay()) {
            $series[$day->toDateString()] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return $series;
    }

    /** Whole-number percentage of $part in $whole, or null when there is nothing to divide. */
    public static function rate(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }
}

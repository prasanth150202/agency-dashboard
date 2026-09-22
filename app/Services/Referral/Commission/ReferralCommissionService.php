<?php

namespace App\Services\Referral\Commission;

use App\Models\Partners\Partner;
use App\Models\PlatformSetting;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Store;
use App\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Revenue event -> commission eligibility -> commission.
 *
 * 1. Revenue events: verified BRIX billing for a referred store is recorded
 *    once (idempotent on source + external_event_id + revenue_type),
 *    regardless of commission settings — history is never destroyed.
 * 2. Eligibility: an event is commissionable only if the agency's own
 *    revenue-source setting includes its type and the agency's commission
 *    configuration allows it.
 * 3. Commission: the amount is computed in integer cents at the agency's
 *    rate as of now, and the rate + rule are frozen on the row.
 *
 * An event that already exists is never touched again: switching the
 * source setting changes what is commissioned from then on, but never
 * rewrites, deletes or retro-fits past events. Nothing here touches
 * payouts, the legacy `transactions` table, or BRIX data.
 */
class ReferralCommissionService
{
    /** @var array<int, array<string, mixed>> */
    private array $agencyCache = [];

    /** @var array<int, Store|null> */
    private array $storeCache = [];

    /** @param  list<RevenueSource>|null  $sources  override for tests */
    public function __construct(private ?array $sources = null) {}

    /**
     * @return array{dry_run: bool, leads: int, events_created: int, events_existing: int, commissions_created: int, commission_total: string, skipped: array<string,int>, not_commissioned: array<string,int>, sources: array<string,string>}
     */
    public function accrue(bool $write = false, ?int $leadId = null): array
    {
        $summary = [
            'dry_run' => ! $write, 'leads' => 0, 'events_created' => 0, 'events_existing' => 0,
            'commissions_created' => 0, 'commission_total' => '0.00', 'skipped' => [], 'not_commissioned' => [], 'sources' => [],
        ];

        $sources = $this->sources ?? [new SubscriptionRevenueSource, new UsageRevenueSource];
        $holdingDays = PlatformSetting::current()->commission_holding_period_days;
        $totalCents = 0;
        $seen = [];
        $this->agencyCache = [];
        $this->storeCache = [];

        Lead::query()
            ->whereNotNull('shop_domain')
            ->whereNotNull('installed_at')
            ->when($leadId, fn ($q) => $q->whereKey($leadId))
            ->chunkById(200, function (Collection $leads) use ($sources, $holdingDays, $write, &$summary, &$totalCents, &$seen) {
                $summary['leads'] += $leads->count();
                $byShop = $leads->keyBy(fn (Lead $l) => strtolower($l->shop_domain));

                foreach ($sources as $source) {
                    $result = $source->collect($leads);

                    if (! $result->verifiable) {
                        // Nothing is created from a source that can't be verified.
                        $summary['sources'][$source->key()] = $result->reason ?? 'not_verifiable';

                        continue;
                    }

                    $summary['sources'][$source->key()] ??= 'ok';

                    foreach ($result->events as $event) {
                        $this->process($event, $byShop->get($event->shopDomain), $holdingDays, $write, $summary, $totalCents, $seen);
                    }
                }
            });

        $summary['commission_total'] = DecimalMoney::format($totalCents);

        return $summary;
    }

    private function process(RevenueEvent $event, ?Lead $lead, int $holdingDays, bool $write, array &$summary, int &$totalCents, array &$seen): void
    {
        // Only a genuine Phase 3 referred lead can be commissionable — a
        // click, an existing store or a plan never is.
        if (! $lead) {
            $this->tally($summary['skipped'], 'no_referred_lead');

            return;
        }

        $store = $this->storeFor($lead);

        if (! $store) {
            $this->tally($summary['skipped'], 'store_not_linked');

            return;
        }

        if ((int) $store->agency_id !== (int) $lead->agency_id) {
            $this->tally($summary['skipped'], 'store_owned_by_other_agency');

            return;
        }

        if ($event->occurredAt->lessThan($lead->installed_at)) {
            $this->tally($summary['skipped'], 'before_referral_install');

            return;
        }

        $key = "{$event->source}|{$event->externalEventId}|{$event->revenueType}";

        if (isset($seen[$key]) || ReferralRevenueEvent::where('source', $event->source)
            ->where('external_event_id', $event->externalEventId)
            ->where('revenue_type', $event->revenueType)->exists()) {
            $summary['events_existing']++;

            return;
        }
        $seen[$key] = true;

        $decision = $this->decide($event, $lead, $store);
        $revenueCents = DecimalMoney::toCents($event->amount);
        $commissionCents = $decision['rate'] ? DecimalMoney::percentOf($revenueCents, $decision['rate']['hundredths']) : 0;

        if ($decision['rate'] && $commissionCents <= 0) {
            $decision = ['rate' => null, 'reason' => 'zero_commission', 'mode' => $decision['mode']];
        }

        $summary['events_created']++;

        if ($decision['rate']) {
            $summary['commissions_created']++;
            $totalCents += $commissionCents;
        } else {
            $this->tally($summary['not_commissioned'], $decision['reason']);
        }

        if (! $write) {
            return;
        }

        try {
            DB::transaction(function () use ($event, $lead, $store, $decision, $revenueCents, $commissionCents, $holdingDays) {
                $revenue = ReferralRevenueEvent::create([
                    'agency_id' => $lead->agency_id,
                    'store_id' => $store->id,
                    'lead_id' => $lead->id,
                    'shop_domain' => $store->shop_domain,
                    'revenue_type' => $event->revenueType,
                    'source' => $event->source,
                    'external_event_id' => $event->externalEventId,
                    'revenue_amount' => DecimalMoney::format($revenueCents),
                    'currency' => $event->currency,
                    'occurred_at' => $event->occurredAt,
                    'status' => ReferralRevenueEvent::STATUS_VERIFIED,
                    'metadata' => $event->metadata + [
                        'source_mode_at_processing' => $decision['mode'],
                        'commission_decision' => $decision['rate'] ? 'commissioned' : 'not_commissioned',
                        'commission_skip_reason' => $decision['rate'] ? null : $decision['reason'],
                    ],
                ]);

                if (! $decision['rate']) {
                    return;
                }

                ReferralCommission::create([
                    'revenue_event_id' => $revenue->id,
                    'agency_id' => $lead->agency_id,
                    'store_id' => $store->id,
                    'lead_id' => $lead->id,
                    'tracking_link_id' => $lead->tracking_link_id,
                    'revenue_type' => $event->revenueType,
                    'revenue_amount' => DecimalMoney::format($revenueCents),
                    'currency' => $event->currency,
                    'commission_rate' => $decision['rate']['display'],
                    'rate_source' => $decision['rate']['source'],
                    'commission_amount' => DecimalMoney::format($commissionCents),
                    'status' => ReferralCommission::STATUS_PENDING,
                    'available_at' => $event->occurredAt->copy()->addDays($holdingDays),
                    'rule' => [
                        'revenue_source_mode' => $decision['mode'],
                        'rate_hierarchy' => 'store_override > agency_default',
                        'rate_source' => $decision['rate']['source'],
                        'commission_type' => 'percentage',
                        'holding_period_days' => $holdingDays,
                    ],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent run recorded this billing event first: undo our tallies.
            $summary['events_created']--;
            $summary['events_existing']++;

            if ($decision['rate']) {
                $summary['commissions_created']--;
                $totalCents -= $commissionCents;
            } else {
                $summary['not_commissioned'][$decision['reason']]--;
            }
        }
    }

    /**
     * Whether this event earns commission under the agency's own settings.
     *
     * @return array{rate: ?array{hundredths: int, display: string, source: string}, reason: ?string, mode: string}
     */
    private function decide(RevenueEvent $event, Lead $lead, Store $store): array
    {
        $agency = $this->agencyFor((int) $lead->agency_id);
        $mode = $agency['mode'];

        $no = fn (string $reason) => ['rate' => null, 'reason' => $reason, 'mode' => $mode];

        if (! CommissionSourceMode::isValid($mode)) {
            return $no('invalid_source_mode');
        }

        if (! CommissionSourceMode::includes($mode, $event->revenueType)) {
            return $no('source_excluded_by_mode');
        }

        if ($agency['blocked']) {
            return $no($agency['blocked']);
        }

        // Existing hierarchy: store override (when enabled) -> agency rate.
        $rate = number_format((float) $store->effective_commission_rate, 2, '.', '');
        $hundredths = DecimalMoney::percentToHundredths($rate);

        if ($hundredths <= 0 || $hundredths > 10000) {
            return $no('zero_commission');
        }

        return [
            'rate' => [
                'hundredths' => $hundredths,
                'display' => $rate,
                'source' => $store->commission_source === 'custom' ? 'store_override' : 'agency_default',
            ],
            'reason' => null,
            'mode' => $mode,
        ];
    }

    /** @return array{mode: string, blocked: ?string} */
    private function agencyFor(int $agencyId): array
    {
        return $this->agencyCache[$agencyId] ??= (function () use ($agencyId) {
            $agency = Partner::find($agencyId);

            $blocked = match (true) {
                ! $agency => 'agency_missing',
                ! $agency->commission_enabled => 'commission_disabled',
                strtolower((string) $agency->commission_type) !== 'percentage' => 'unsupported_commission_type',
                default => null,
            };

            return ['mode' => CommissionSourceMode::forAgency($agencyId), 'blocked' => $blocked];
        })();
    }

    /** The lead's store, resolved server-side by shop_domain — never by an id from the client. */
    private function storeFor(Lead $lead): ?Store
    {
        return $this->storeCache[$lead->id] ??= Store::where('shop_domain', strtolower($lead->shop_domain))->first();
    }

    private function tally(array &$bucket, string $reason): void
    {
        $bucket[$reason] = ($bucket[$reason] ?? 0) + 1;
    }
}

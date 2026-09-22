<?php

namespace App\Services\Finance;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\Referral\ReferralCommission;
use App\Support\DecimalMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One read view over the agency's two commission ledgers:
 *
 *   legacy `transactions`  ->  App\Models\Commission            (store commissions)
 *   `referral_commissions` ->  App\Models\Referral\ReferralCommission
 *
 * Neither ledger is copied into or altered by the other; this layer only
 * reads both and presents them together. Every query is pinned to the
 * organisation's own brix_agency_id, which the server resolved from the
 * session.
 *
 * Legacy commissions carry no currency of their own and are, as everywhere
 * else in this app, in the organisation's currency. Referral commissions
 * carry the currency they were billed in. Amounts in different currencies
 * are never added together: every total is `[currency => integer cents]`.
 */
class UnifiedCommissionService
{
    public function __construct(private readonly Organisation $organisation) {}

    public function currency(): string
    {
        return strtoupper($this->organisation->currency);
    }

    private function agencyId(): int
    {
        return (int) $this->organisation->brix_agency_id;
    }

    /**
     * @return array{total_earned: array<string,int>, pending: array<string,int>, available: array<string,int>, in_payout: array<string,int>, paid: array<string,int>}
     */
    public function summary(): array
    {
        $finance = $this->organisation->finance();
        $legacyInPayout = $this->organisation->commissions()->where('commission_status', Commission::STATUS_IN_PAYOUT)->sum('agency_commission');

        $legacy = [
            'total_earned' => $finance->lifetimeEarnings(),
            'pending' => $finance->pendingCommission(),
            'available' => $finance->storeCommissionsAvailable(),
            'in_payout' => (float) $legacyInPayout,
            'paid' => $finance->commissionsPaid(),
        ];

        $summary = [];

        foreach ($legacy as $bucket => $amount) {
            $summary[$bucket] = $this->addCents([], $this->currency(), DecimalMoney::toCents(round($amount, 2)));
        }

        $referral = [
            'total_earned' => $this->referral()->whereIn('status', ReferralCommission::EARNED_STATUSES),
            'pending' => $this->referral()->stillPending(),
            'available' => $this->referral()->effectivelyAvailable(),
            'in_payout' => $this->referral()->where('status', ReferralCommission::STATUS_IN_PAYOUT),
            'paid' => $this->referral()->where('status', ReferralCommission::STATUS_PAID),
        ];

        foreach ($referral as $bucket => $query) {
            foreach ($query->selectRaw('currency, SUM(commission_amount) as total')->groupBy('currency')->get() as $row) {
                $summary[$bucket] = $this->addCents($summary[$bucket], strtoupper($row->currency), DecimalMoney::toCents($row->total ?? 0));
            }
        }

        return array_map(fn (array $byCurrency) => array_filter($byCurrency, fn (int $cents) => $cents !== 0), $summary);
    }

    /**
     * What a payout in the organisation's currency can claim right now, in
     * cents: withdrawable store commissions plus withdrawable referral
     * commissions billed in that same currency.
     */
    public function payableCents(): int
    {
        $finance = $this->organisation->finance();

        return DecimalMoney::toCents(round($finance->storeCommissionsAvailable(), 2))
            + DecimalMoney::toCents(round($finance->referralCommissionsAvailable(), 2));
    }

    /**
     * Withdrawable referral commissions in currencies other than the
     * organisation's. No exchange rate exists here, so they cannot join a
     * payout — surfaced so they are never silently lost.
     *
     * @return array<string, int> currency => cents
     */
    public function unpayable(): array
    {
        $out = [];

        $rows = $this->referral()
            ->effectivelyAvailable()
            ->where('currency', '!=', $this->currency())
            ->selectRaw('currency, SUM(commission_amount) as total')
            ->groupBy('currency')
            ->get();

        foreach ($rows as $row) {
            $out[strtoupper($row->currency)] = DecimalMoney::toCents($row->total ?? 0);
        }

        return array_filter($out, fn (int $cents) => $cents !== 0);
    }

    /**
     * Claims whole commission rows, oldest first across BOTH ledgers, until
     * their sum reaches $amountCents or nothing claimable is left. Rows are
     * locked for update, so this MUST run inside the caller's transaction, and
     * the caller must link them to the payout and move them to in_payout in
     * that same transaction. Only commissions in the organisation's currency
     * are claimable.
     *
     * @return array{store: Collection<int, Commission>, referral: Collection<int, ReferralCommission>, cents: int}
     */
    public function claim(int $amountCents): array
    {
        $candidates = $this->organisation->commissions()
            ->effectivelyAvailable()
            ->orderBy('created_at')->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (Commission $c) => ['row' => $c, 'cents' => DecimalMoney::toCents((string) $c->agency_commission), 'at' => $c->created_at?->getTimestamp() ?? 0, 'kind' => 'store'])
            ->concat(
                $this->referral()
                    ->payableIn($this->currency())
                    ->orderBy('created_at')->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->map(fn (ReferralCommission $c) => ['row' => $c, 'cents' => DecimalMoney::toCents((string) $c->commission_amount), 'at' => $c->created_at?->getTimestamp() ?? 0, 'kind' => 'referral'])
            )
            ->sort(fn (array $a, array $b) => [$a['at'], $a['kind'], $a['row']->id] <=> [$b['at'], $b['kind'], $b['row']->id]);

        $store = collect();
        $referral = collect();
        $sum = 0;

        foreach ($candidates as $candidate) {
            if ($sum >= $amountCents) {
                break;
            }

            $candidate['kind'] === 'store' ? $store->push($candidate['row']) : $referral->push($candidate['row']);
            $sum += $candidate['cents'];
        }

        return ['store' => $store, 'referral' => $referral, 'cents' => $sum];
    }

    /**
     * Every commission, newest first, from both ledgers.
     *
     * @param  array{search?: ?string, status?: ?string, store?: mixed, source?: ?string}  $filters
     * @return Collection<int, LedgerEntry>
     */
    public function entries(?Carbon $from = null, ?Carbon $to = null, array $filters = []): Collection
    {
        $source = $filters['source'] ?? null;
        $entries = collect();

        if ($source !== LedgerEntry::SOURCE_REFERRAL) {
            $entries = $entries->merge($this->storeEntries($from, $to, $filters['store'] ?? null));
        }

        if ($source !== LedgerEntry::SOURCE_STORE) {
            $entries = $entries->merge($this->referralEntries($from, $to, $filters['store'] ?? null));
        }

        return $entries
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn (Collection $c) => $c->filter(fn (LedgerEntry $e) => $e->status === $filters['status']))
            ->when(! empty($filters['search']), fn (Collection $c) => $c->filter(fn (LedgerEntry $e) => $this->matches($e, (string) $filters['search'])))
            ->sort(fn (LedgerEntry $a, LedgerEntry $b) => [$b->date->getTimestamp(), $b->id] <=> [$a->date->getTimestamp(), $a->id])
            ->values();
    }

    /** @return Collection<int, LedgerEntry> */
    private function storeEntries(?Carbon $from, ?Carbon $to, mixed $store): Collection
    {
        return $this->organisation->commissions()
            ->with(['store', 'payouts'])
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($store && $store !== 'all', fn ($q) => $q->where('store_id', $store))
            ->get()
            ->map(fn (Commission $c) => new LedgerEntry(
                source: LedgerEntry::SOURCE_STORE,
                id: $c->id,
                reference: 'TXN-'.$c->id,
                storeId: $c->store_id,
                storeName: $c->store?->name ?? 'Unknown store',
                shopDomain: $c->store?->shop_domain,
                baseAmount: number_format((float) $c->gross_amount, 2, '.', ''),
                rate: (string) (float) $c->commission_rate,
                amount: number_format((float) $c->agency_commission, 2, '.', ''),
                currency: $this->currency(),
                status: $c->visual_status,
                statusLabel: $c->status_label,
                badge: $c->badge_status,
                date: $c->transaction_date,
                availableAt: $c->available_at,
                inPayoutAt: $c->payouts->first()?->pivot?->created_at,
                paidAt: $c->payouts->firstWhere('status', 'paid')?->paid_at,
            ));
    }

    /** @return Collection<int, LedgerEntry> */
    private function referralEntries(?Carbon $from, ?Carbon $to, mixed $store): Collection
    {
        return $this->referral()
            ->with(['store', 'revenueEvent', 'trackingLink', 'payouts'])
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($store && $store !== 'all', fn ($q) => $q->where('store_id', $store))
            ->get()
            ->map(function (ReferralCommission $c) {
                // A rejected/cancelled payout leaves its pivot row behind as
                // history; only a live or paid claim describes this commission.
                $claim = $c->payouts
                    ->filter(fn (Payout $p) => in_array($p->status, [...Payout::RESERVING_STATUSES, Payout::STATUS_PAID], true))
                    ->sortByDesc('requested_at')
                    ->first();

                $status = match ($c->effective_status) {
                    ReferralCommission::STATUS_REVERSED => 'refunded',
                    default => $c->effective_status,
                };

                return new LedgerEntry(
                    source: LedgerEntry::SOURCE_REFERRAL,
                    id: $c->id,
                    reference: 'REF-'.$c->id,
                    storeId: $c->store_id,
                    storeName: $c->store?->name ?? 'Unknown store',
                    shopDomain: $c->store?->shop_domain,
                    baseAmount: number_format((float) $c->revenue_amount, 2, '.', ''),
                    rate: (string) (float) $c->commission_rate,
                    amount: number_format((float) $c->commission_amount, 2, '.', ''),
                    currency: strtoupper($c->currency),
                    status: $status,
                    statusLabel: match ($status) {
                        'eligible' => 'Eligible',
                        'in_payout' => 'In Payout',
                        'refunded' => 'Reversed',
                        default => ucfirst($status),
                    },
                    badge: match ($status) {
                        'eligible' => 'eligible',
                        'paid' => 'paid',
                        'in_payout' => 'in_payout',
                        'pending' => 'attention',
                        'refunded', 'cancelled' => 'offline',
                        default => 'inactive',
                    },
                    date: ($c->revenueEvent?->occurred_at ?? $c->created_at)->copy()->startOfDay(),
                    availableAt: $c->available_at,
                    inPayoutAt: $claim?->pivot?->created_at,
                    paidAt: $claim?->status === Payout::STATUS_PAID ? $claim->paid_at : null,
                    via: $c->trackingLink?->name,
                );
            });
    }

    private function referral()
    {
        return ReferralCommission::query()->where('agency_id', $this->agencyId());
    }

    private function matches(LedgerEntry $e, string $term): bool
    {
        $needle = mb_strtolower(trim($term));

        // "TXN-123" or a bare "123" finds that store commission by id;
        // anything else (e.g. "REF-1") is matched as text only.
        $numericId = preg_match('/^\s*(?:txn-?)?(\d+)\s*$/i', $term, $m) ? (int) $m[1] : 0;

        return ($numericId > 0 && $e->source === LedgerEntry::SOURCE_STORE && $e->id === $numericId)
            || str_contains(mb_strtolower($e->reference), $needle)
            || str_contains(mb_strtolower($e->storeName), $needle)
            || str_contains(mb_strtolower((string) $e->shopDomain), $needle)
            || str_contains($e->amount, $needle)
            || str_contains($e->baseAmount, $needle);
    }

    /** @param  array<string,int>  $map */
    private function addCents(array $map, string $currency, int $cents): array
    {
        $map[$currency] = ($map[$currency] ?? 0) + $cents;

        return $map;
    }
}

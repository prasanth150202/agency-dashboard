<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** 
 * The single source of truth for every agency balance figure shown in
 * the dashboard. Every number here is computed fresh from commissions
 * and payouts on each call — nothing is trusted from a stored/mutable
 * "balance" column, and nothing is ever trusted from the browser.
 */
class AgencyFinanceService
{
    public function __construct(private readonly Organisation $organisation) {}

    /**
     * Available Balance = commissions whose effective status is
     * "available" right now.
     *
     * A commission counts as "available" once its holding period has
     * lifted (Commission::effective_status), even if the stored status
     * column hasn't been flipped by a background job yet. Once a payout
     * claims a commission (see claimCommissionsForPayout()), that
     * commission's status moves to IN_PAYOUT/PAID and this sum already
     * excludes it — so, unlike before commission-level claiming existed,
     * this must NOT also subtract reservedForPendingPayouts()/paid
     * payout totals, or a claimed commission's value would be counted
     * as "reserved" twice.
     */
    public function availableBalance(): float
    {
        $available = Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->effectivelyAvailable()
            ->sum('commission_amount');

        return round((float) $available, 2);
    }

    /**
     * Money currently earmarked by payout requests still awaiting a
     * decision — for display only (e.g. "Pending Payouts" /
     * "Processing" KPI tiles). Not part of availableBalance(); the
     * commissions those payouts claimed are already excluded from it via
     * their own IN_PAYOUT status.
     */
    public function reservedForPendingPayouts(): float
    {
        return (float) Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->whereIn('status', Payout::RESERVING_STATUSES)
            ->sum('amount');
    }

    public function pendingCommission(): float
    {
        return (float) Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->stillPending()
            ->sum('commission_amount');
    }

    /** Total commission ever earned, excluding anything reversed by a refund. */
    public function lifetimeEarnings(): float
    {
        return (float) Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', '!=', Commission::STATUS_REFUNDED)
            ->sum('commission_amount');
    }

    public function thisMonthEarnings(): float
    {
        return (float) Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('commission_amount');
    }

    public function paidThisMonth(): float
    {
        return (float) Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PAID)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
    }

    /** Lifetime total ever actually paid out to this agency. */
    public function totalPaid(): float
    {
        return (float) Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PAID)
            ->sum('amount');
    }

    /**
     * Sum of commissions that have been fully paid — distinct from
     * totalPaid() (sum of Payout rows), since a paid payout can bundle
     * several commissions and this is the commission-side view of the
     * same fact. Used by the Commissions page's "Paid" KPI.
     */
    public function commissionsPaid(): float
    {
        return (float) Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Commission::STATUS_PAID)
            ->sum('commission_amount');
    }

    /**
     * Greedily claims the oldest currently-claimable commissions (locked
     * for update — must be called inside a transaction) until their sum
     * covers $amount, or until claimable commissions run out. No
     * commission is ever split across two payouts — whole rows only.
     * Callers are responsible for linking + status changes on the
     * returned rows inside the same transaction.
     */
    public function claimCommissionsForPayout(float $amount): Collection
    {
        $rows = Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->effectivelyAvailable()
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $claimed = collect();
        $sum = 0.0;

        foreach ($rows as $row) {
            if ($sum >= $amount) {
                break;
            }
            $claimed->push($row);
            $sum += (float) $row->commission_amount;
        }

        return $claimed;
    }

    public function processingPayouts(): float
    {
        return (float) Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PROCESSING)
            ->sum('amount');
    }

    public function pendingPayouts(): float
    {
        return (float) Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PENDING)
            ->sum('amount');
    }

    public function lastPayout(): ?Payout
    {
        return Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PAID)
            ->orderByDesc('paid_at')
            ->first();
    }

    public function minimumPayoutAmount(): float
    {
        return (float) PlatformSetting::current()->minimum_payout_amount;
    }

    public function canRequestPayout(): bool
    {
        if ($this->hasPendingOrProcessingPayout()) {
            return false;
        }

        return $this->availableBalance() >= $this->minimumPayoutAmount();
    }

    public function hasPendingOrProcessingPayout(): bool
    {
        return Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->whereIn('status', Payout::RESERVING_STATUSES)
            ->exists();
    }

    public function holdingPeriodDays(): int
    {
        return (int) PlatformSetting::current()->commission_holding_period_days;
    }

    public function commissionAvailableAt(?Carbon $transactionDate = null): Carbon
    {
        return ($transactionDate ?? now())->copy()->addDays($this->holdingPeriodDays());
    }
}

<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;

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
     * Available Balance = realized commission still available
     *                    - already-paid-out payouts
     *                    - payouts currently pending/processing (reserved)
     *
     * A commission counts as "available" once its holding period has
     * lifted (Commission::effective_status), even if the stored status
     * column hasn't been flipped by a background job yet.
     */
    public function availableBalance(): float
    {
        $available = Commission::query()
            ->where('organisation_id', $this->organisation->id)
            ->effectivelyAvailable()
            ->sum('commission_amount');

        $paidOut = Payout::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('status', Payout::STATUS_PAID)
            ->sum('amount');

        $reserved = $this->reservedForPendingPayouts();

        return round((float) $available - (float) $paidOut - $reserved, 2);
    }

    /** Money already earmarked by payout requests still awaiting a decision. */
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

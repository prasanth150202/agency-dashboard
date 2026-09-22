<?php

namespace App\Models\Referral;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `referral_commissions` — the agency's earned amount from exactly one
 * ReferralRevenueEvent, with the rate and rule frozen at creation.
 *
 * Deliberately separate from the legacy `transactions` table (bulk-generated
 * legacy data, not Shopify billing) and not connected to payouts: becoming
 * eligible does not mean paid.
 */
class ReferralCommission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ELIGIBLE = 'eligible';

    /** Claimed by a payout request that has not been paid, rejected or cancelled yet. */
    public const STATUS_IN_PAYOUT = 'in_payout';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that still count as commission the agency has earned. */
    public const EARNED_STATUSES = [self::STATUS_PENDING, self::STATUS_ELIGIBLE, self::STATUS_IN_PAYOUT, self::STATUS_PAID];

    protected $fillable = [
        'revenue_event_id',
        'agency_id',
        'store_id',
        'lead_id',
        'tracking_link_id',
        'revenue_type',
        'revenue_amount',
        'currency',
        'commission_rate',
        'rate_source',
        'commission_amount',
        'status',
        'available_at',
        'rule',
    ];

    protected $casts = [
        'revenue_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'available_at' => 'datetime',
        'rule' => 'array',
    ];

    public function revenueEvent(): BelongsTo
    {
        return $this->belongsTo(ReferralRevenueEvent::class, 'revenue_event_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Partners\Partner::class, 'agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Store::class, 'store_id');
    }

    /** Payouts that have ever claimed this commission (a released claim leaves its row). */
    public function payouts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Payout::class, 'referral_commission_payout', 'referral_commission_id', 'payout_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    /**
     * Commissions the agency can withdraw right now: explicitly eligible,
     * or pending with the holding period already lifted. Mirrors
     * Commission::scopeEffectivelyAvailable on the legacy ledger.
     */
    public function scopeEffectivelyAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_ELIGIBLE)
                ->orWhere(fn (Builder $q2) => $q2->where('status', self::STATUS_PENDING)->where('available_at', '<=', now()));
        });
    }

    /**
     * Withdrawable right now AND in the currency a payout is being made in.
     * A payout has exactly one currency, and no exchange rate exists here, so
     * a commission in any other currency is never claimable into it.
     */
    public function scopePayableIn(Builder $query, string $currency): Builder
    {
        return $query->effectivelyAvailable()->where('currency', strtoupper($currency));
    }

    /** Pending and still inside the holding period. */
    public function scopeStillPending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(fn (Builder $q) => $q->whereNull('available_at')->orWhere('available_at', '>', now()));
    }

    /** A pending row whose holding period has lifted reads as eligible. */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->available_at?->isPast()) {
            return self::STATUS_ELIGIBLE;
        }

        return $this->status;
    }
}

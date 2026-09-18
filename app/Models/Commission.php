<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Commission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_PAYOUT = 'in_payout';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_AGENCY_DEFAULT = 'agency_default';

    public const SOURCE_CUSTOM = 'custom';

    protected $fillable = [
        'organisation_id',
        'store_id',
        'gross_amount',
        'commission_rate',
        'commission_source',
        'commission_amount',
        'brix_amount',
        'status',
        'transaction_date',
        'available_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'brix_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'available_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Payout(s) that have ever claimed this commission — normally at
     * most one active claim at a time (status STATUS_IN_PAYOUT/PAID),
     * but a rejected/cancelled payout releases its claim, so a
     * commission can show more than one row here over its lifetime.
     */
    public function payouts(): BelongsToMany
    {
        return $this->belongsToMany(Payout::class, 'commission_payout')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * The status as the agency should see it right now: a "pending" row
     * whose holding period has lifted reads as "available" without
     * needing a scheduled job to flip the stored column.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->available_at->isPast()) {
            return self::STATUS_AVAILABLE;
        }

        return $this->status;
    }

    public function getCommissionSourceLabelAttribute(): string
    {
        return $this->commission_source === self::SOURCE_CUSTOM ? 'Custom Rate' : 'Agency Rate';
    }

    /**
     * Display-only status, one step more granular than effective_status:
     * a "pending" row whose holding period has lifted shows as "eligible"
     * rather than being folded into "available". This is never used for
     * balance/business logic (effective_status and scopeEffectivelyAvailable
     * still treat the two as one state, correctly — a commission is
     * withdrawable the instant its holding period lifts, whether or not
     * the stored status column has been swept to 'available' yet) — it
     * exists purely so the ledger can show the agency which of those two
     * real moments a commission is in.
     */
    public function getVisualStatusAttribute(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->available_at->isPast()) {
            return 'eligible';
        }

        return $this->status;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->visual_status) {
            'eligible' => 'Eligible',
            self::STATUS_IN_PAYOUT => 'In Payout',
            self::STATUS_REFUNDED => 'Reversed',
            default => ucfirst($this->visual_status),
        };
    }

    public function getBadgeStatusAttribute(): string
    {
        return match ($this->visual_status) {
            self::STATUS_AVAILABLE => 'active',
            'eligible' => 'eligible',
            self::STATUS_PAID => 'paid',
            self::STATUS_IN_PAYOUT => 'in_payout',
            self::STATUS_PENDING => 'attention',
            self::STATUS_REFUNDED, self::STATUS_CANCELLED => 'offline',
            default => 'inactive',
        };
    }

    /**
     * Commissions whose effective status is "available" — realized and
     * still eligible to be withdrawn (i.e. not yet paid out).
     */
    public function scopeEffectivelyAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_AVAILABLE)
                ->orWhere(function (Builder $q2) {
                    $q2->where('status', self::STATUS_PENDING)->where('available_at', '<=', now());
                });
        });
    }

    public function scopeStillPending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->where('available_at', '>', now());
    }
}

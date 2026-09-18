<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The real, canonical `transactions` table (17,905 rows) — one row per
 * store payment to BRIX that generates agency commission. Holding-period
 * fields (commission_status, available_at) were added on top of the real
 * schema by the single-database merge so this and a future admin
 * approval flow act on the exact same row; transactions.status (a
 * different column) is the underlying charge's own success/pending/
 * failed/refunded outcome, untouched here.
 */
class Commission extends Model
{
    protected $table = 'transactions';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

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
        'agency_id',
        'store_id',
        'subscription_id',
        'gross_amount',
        'commission_rate',
        'commission_source',
        'agency_commission',
        'brix_revenue',
        'commission_status',
        'available_at',
        'type',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'agency_commission' => 'decimal:2',
            'brix_revenue' => 'decimal:2',
            'available_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'agency_id', 'brix_agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Payout(s) that have ever claimed this commission — normally at
     * most one active claim at a time (status STATUS_IN_PAYOUT/PAID),
     * but a rejected/cancelled payout releases its claim, so a
     * commission can show more than one row here over its lifetime.
     */
    public function payouts(): BelongsToMany
    {
        return $this->belongsToMany(Payout::class, 'transaction_payout', 'transaction_id', 'payout_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /** Convenience alias — real columns are the split figures, not one amount. */
    public function getCommissionAmountAttribute(): float
    {
        return (float) $this->agency_commission;
    }

    public function getBrixAmountAttribute(): float
    {
        return (float) $this->brix_revenue;
    }

    /** No separate date column on the real table — created_at doubles as it. */
    public function getTransactionDateAttribute(): \Illuminate\Support\Carbon
    {
        return $this->created_at->copy()->startOfDay();
    }

    /**
     * The status as the agency should see it right now: a "pending" row
     * whose holding period has lifted reads as "available" without
     * needing a scheduled job to flip the stored column.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->commission_status === self::STATUS_PENDING && $this->available_at?->isPast()) {
            return self::STATUS_AVAILABLE;
        }

        return $this->commission_status;
    }

    public function getCommissionSourceLabelAttribute(): string
    {
        return $this->commission_source === self::SOURCE_CUSTOM ? 'Custom Rate' : 'Agency Rate';
    }

    /**
     * Display-only status, one step more granular than effective_status:
     * a "pending" row whose holding period has lifted shows as "eligible"
     * rather than being folded into "available". Never used for balance/
     * business logic — exists purely so the ledger can show the agency
     * which of those two real moments a commission is in.
     */
    public function getVisualStatusAttribute(): string
    {
        if ($this->commission_status === self::STATUS_PENDING && $this->available_at?->isPast()) {
            return 'eligible';
        }

        return $this->commission_status;
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
            $q->where('commission_status', self::STATUS_AVAILABLE)
                ->orWhere(function (Builder $q2) {
                    $q2->where('commission_status', self::STATUS_PENDING)->where('available_at', '<=', now());
                });
        });
    }

    public function scopeStillPending(Builder $query): Builder
    {
        return $query->where('commission_status', self::STATUS_PENDING)->where('available_at', '>', now());
    }
}

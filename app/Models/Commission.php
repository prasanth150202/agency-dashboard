<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_ADJUSTED = 'adjusted';

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

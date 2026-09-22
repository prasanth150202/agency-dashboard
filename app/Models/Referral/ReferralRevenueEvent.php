<?php

namespace App\Models\Referral;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * `referral_revenue_events` — one piece of verified BRIX billing earned by
 * a referred store. A billing fact: never edited or deleted by commission
 * or configuration changes. The same event (source + external_event_id +
 * revenue_type) can only exist once.
 *
 * A future reversal is a second row with a negative revenue_amount and
 * reversal_of_id set; no refund source exists yet, so none is created.
 */
class ReferralRevenueEvent extends Model
{
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_USAGE = 'usage';

    public const TYPES = [self::TYPE_SUBSCRIPTION, self::TYPE_USAGE];

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REVERSED = 'reversed';

    /** BRIX bills Shopify usage in USD (hardcoded in the Shopify app). */
    public const USAGE_CURRENCY = 'USD';

    protected $fillable = [
        'agency_id',
        'store_id',
        'lead_id',
        'shop_domain',
        'revenue_type',
        'source',
        'external_event_id',
        'revenue_amount',
        'currency',
        'occurred_at',
        'status',
        'reversal_of_id',
        'metadata',
    ];

    protected $casts = [
        'revenue_amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

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

    public function commission(): HasOne
    {
        return $this->hasOne(ReferralCommission::class, 'revenue_event_id');
    }
}

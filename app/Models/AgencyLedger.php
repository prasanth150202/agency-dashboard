<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The real, canonical `agency_ledger` table (15,779 rows) — the
 * append-only audit trail of every event that moves a partner's balance:
 * commissions realized, payouts paid, refunds, adjustments. Financial
 * records are never deleted — corrections are new rows. Note there is no
 * `updated_at` column on the real table (rows are never updated).
 */
class AgencyLedger extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public const TYPE_COMMISSION = 'COMMISSION';

    public const TYPE_PAYOUT = 'PAYOUT';

    public const TYPE_REFUND = 'REFUND';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const TYPE_REVERSAL = 'REVERSAL';

    protected $table = 'agency_ledger';

    protected $fillable = [
        'agency_id',
        'store_id',
        'transaction_id',
        'payout_id',
        'type',
        'amount',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'agency_id', 'brix_agency_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyLedger extends Model
{
    public const TYPE_COMMISSION = 'COMMISSION';

    public const TYPE_PAYOUT = 'PAYOUT';

    public const TYPE_REFUND = 'REFUND';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    protected $table = 'agency_ledger';

    protected $fillable = [
        'organisation_id',
        'store_id',
        'transaction_id',
        'type',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

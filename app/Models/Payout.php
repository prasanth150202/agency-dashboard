<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_PAID,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
    ];

    /** Statuses that still reserve funds out of the available balance. */
    public const RESERVING_STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING];

    protected $fillable = [
        'organisation_id',
        'store_id',
        'payout_code',
        'date',
        'description',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payout_account_id',
        'provider',
        'provider_payout_id',
        'requested_at',
        'processing_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payout $payout) {
            if (empty($payout->payout_code)) {
                $payout->payout_code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'PAY-'.random_int(1000, 9999);
        } while (static::where('payout_code', $code)->exists());

        return $code;
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return Str::headline($this->status);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'bank_transfer' => 'Bank Transfer',
            'upi' => 'UPI',
            default => 'Manual',
        };
    }

    /**
     * Move this payout through its lifecycle. Every transition here is
     * shared between the agency and BRIX admin side — there is only ever
     * one payout record, so both sides always see the same status.
     */
    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING, 'processing_at' => now()]);

        $this->organisation->notifications()->create([
            'type' => 'payout_processing',
            'title' => 'Payout is being processed',
            'message' => "Your payout {$this->payout_code} of ₹".number_format((float) $this->amount, 2).' is now being processed.',
        ]);
    }

    public function markPaid(?string $providerPayoutId = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'provider_payout_id' => $providerPayoutId ?? $this->provider_payout_id,
        ]);

        AgencyLedger::create([
            'organisation_id' => $this->organisation_id,
            'transaction_id' => $this->id,
            'type' => AgencyLedger::TYPE_PAYOUT,
            'amount' => -$this->amount,
            'description' => "Payout {$this->payout_code} paid",
        ]);

        $this->organisation->notifications()->create([
            'type' => 'payout_paid',
            'title' => 'Payout paid',
            'message' => 'Your payout of ₹'.number_format((float) $this->amount, 2).' has been paid.',
        ]);
    }

    public function markRejected(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->organisation->notifications()->create([
            'type' => 'payout_rejected',
            'title' => 'Payout rejected',
            'message' => "Your payout request {$this->payout_code} was rejected: {$reason}",
        ]);
    }
}

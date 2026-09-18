<?php

namespace App\Models;

use App\Support\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Payout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
        self::STATUS_PAID,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    /** Statuses that still reserve funds out of the available balance. */
    public const RESERVING_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PROCESSING];

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
        'approved_at',
        'processing_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    /**
     * Commissions this payout has claimed — see commission_payout and
     * Commission::STATUS_IN_PAYOUT.
     */
    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class, 'commission_payout')
            ->withPivot('amount')
            ->withTimestamps();
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
     * one payout record, so both sides always see the same status. Each
     * guards against running from the wrong current status so a retried
     * or duplicate call is a no-op rather than double-writing the ledger
     * or sending a duplicate notification.
     */
    public function markProcessing(): void
    {
        if ($this->status !== self::STATUS_PENDING && $this->status !== self::STATUS_APPROVED) {
            return;
        }

        $this->update(['status' => self::STATUS_PROCESSING, 'processing_at' => now()]);

        $this->organisation->notifications()->create([
            'type' => 'payout_processing',
            'title' => 'Payout is being processed',
            'message' => "Your payout {$this->payout_code} of ".Currency::format($this->amount, $this->currency).' is now being processed.',
        ]);
    }

    public function markPaid(?string $providerPayoutId = null): void
    {
        if ($this->status === self::STATUS_PAID) {
            return;
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'provider_payout_id' => $providerPayoutId ?? $this->provider_payout_id,
        ]);

        // The commissions this payout claimed are now truly spent.
        $this->commissions()->update(['status' => Commission::STATUS_PAID]);

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
            'message' => 'Your payout of '.Currency::format($this->amount, $this->currency).' has been paid.',
        ]);
    }

    public function markRejected(string $reason): void
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED], true)) {
            return;
        }

        $this->releaseClaimedCommissions();

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

    /**
     * The one status transition the agency itself can trigger — withdraw
     * their own still-pending request. Only valid from PENDING: once
     * BRIX has approved/started processing it, the agency can no longer
     * unilaterally cancel it.
     */
    public function markCancelled(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            return;
        }

        $this->releaseClaimedCommissions();

        $this->update(['status' => self::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }

    /**
     * Hands back every commission this payout had claimed to STATUS_AVAILABLE
     * so it can be claimed by a future payout — used when a payout is
     * rejected or cancelled instead of paid. The pivot row itself is left
     * in place as a historical record of the attempt.
     */
    private function releaseClaimedCommissions(): void
    {
        Commission::whereIn('id', $this->commissions()->pluck('commissions.id'))
            ->where('status', Commission::STATUS_IN_PAYOUT)
            ->update(['status' => Commission::STATUS_AVAILABLE]);
    }
}

<?php

namespace App\Models;

use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * The real, canonical `payouts` table (475 rows) — agency-level, with a
 * full RAZORPAYX/idempotency-aware approval workflow already built in.
 * This is what a future admin approval screen acts on directly; there is
 * no separate local payouts table any more.
 */
class Payout extends Model
{
    public $timestamps = false;

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
        'agency_id',
        'payout_code',
        'payout_account_id',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'payment_method',
        'status',
        'provider',
        'provider_payout_id',
        'idempotency_key',
        'requested_at',
        'approved_at',
        'processing_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
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

            if (empty($payout->idempotency_key)) {
                $payout->idempotency_key = (string) Str::uuid();
            }

            $payout->provider ??= 'MANUAL';
            $payout->requested_at ??= now();
            $payout->period_start ??= now()->toDateString();
            $payout->period_end ??= now()->toDateString();
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
        return $this->belongsTo(Organisation::class, 'agency_id', 'brix_agency_id');
    }

    /**
     * The real partner record — unlike organisation(), this always
     * resolves (every payout.agency_id references a real agencies row).
     * The admin side reads partner(), never organisation(), since not
     * every agency has a bridged Organisation login yet.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Partners\Partner::class, 'agency_id');
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    /**
     * Commissions this payout has claimed — see transaction_payout and
     * Commission::STATUS_IN_PAYOUT.
     */
    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class, 'transaction_payout', 'payout_id', 'transaction_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /** Convenience alias — the real column is `notes`, not `description`. */
    public function getDescriptionAttribute(): ?string
    {
        return $this->notes;
    }

    /** Convenience alias — the real table has no single `date` column. */
    public function getDateAttribute(): \Illuminate\Support\Carbon
    {
        return $this->requested_at ?? $this->period_end;
    }

    public function getStatusLabelAttribute(): string
    {
        return Str::headline($this->status);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method ?: 'Manual';
    }

    /**
     * Move this payout through its lifecycle. Every transition here is
     * shared between the partner and BRIX admin side — there is only
     * ever one payout record, so both sides always see the same status.
     * Each guards against running from the wrong current status so a
     * retried or duplicate call is a no-op rather than double-writing
     * the ledger or sending a duplicate notification.
     */
    public function markProcessing(): void
    {
        if ($this->status !== self::STATUS_PENDING && $this->status !== self::STATUS_APPROVED) {
            return;
        }

        $this->update(['status' => self::STATUS_PROCESSING, 'processing_at' => now()]);

        $this->organisation?->notifications()->create([
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
        $this->commissions()->update(['commission_status' => Commission::STATUS_PAID]);

        AgencyLedger::create([
            'agency_id' => $this->agency_id,
            'payout_id' => $this->id,
            'type' => AgencyLedger::TYPE_PAYOUT,
            'amount' => -$this->amount,
            'description' => "Payout {$this->payout_code} paid",
            'created_at' => now(),
        ]);

        $this->organisation?->notifications()->create([
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

        $this->organisation?->notifications()->create([
            'type' => 'payout_rejected',
            'title' => 'Payout rejected',
            'message' => "Your payout request {$this->payout_code} was rejected: {$reason}",
        ]);
    }

    /**
     * The one status transition the partner itself can trigger —
     * withdraw their own still-pending request. Only valid from PENDING:
     * once BRIX has approved/started processing it, the partner can no
     * longer unilaterally cancel it.
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
     * Hands back every commission this payout had claimed to
     * STATUS_AVAILABLE so it can be claimed by a future payout — used
     * when a payout is rejected or cancelled instead of paid. The pivot
     * row itself is left in place as a historical record of the attempt.
     */
    private function releaseClaimedCommissions(): void
    {
        Commission::whereIn('id', $this->commissions()->pluck('transactions.id'))
            ->where('commission_status', Commission::STATUS_IN_PAYOUT)
            ->update(['commission_status' => Commission::STATUS_AVAILABLE]);
    }
}

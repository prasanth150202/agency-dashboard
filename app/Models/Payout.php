<?php

namespace App\Models;

use App\Models\Referral\ReferralCommission;
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

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
        self::STATUS_PAID,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    /** Statuses that still reserve funds out of the available balance. */
    public const RESERVING_STATUSES = [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED, self::STATUS_PROCESSING];

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
        'requested_by',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'processing_at',
        'paid_at',
        'transfer_reference',
        'transfer_date',
        'paid_amount',
        'paid_currency',
        'paid_by',
        'payment_notes',
        'rejected_at',
        'rejection_reason',
        'rejected_by',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'transfer_date' => 'date',
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\AdminUser::class, 'reviewed_by');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\AdminUser::class, 'approved_by');
    }

    public function rejectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\AdminUser::class, 'rejected_by');
    }

    public function paidByAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\AdminUser::class, 'paid_by');
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

    /**
     * Referral commissions this payout has claimed — see
     * referral_commission_payout and ReferralCommission::STATUS_IN_PAYOUT.
     * The referral counterpart of commissions(); both feed the one payout.
     */
    public function referralCommissions(): BelongsToMany
    {
        return $this->belongsToMany(ReferralCommission::class, 'referral_commission_payout', 'payout_id', 'referral_commission_id')
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

    /**
     * The Admin's first look at a still-pending request — surfaces who is
     * reviewing it and when, without changing anything financial yet.
     * Only valid from PENDING; a no-op otherwise so a duplicate click never
     * clobbers an existing reviewed_at/reviewed_by.
     */
    public function markUnderReview(int $adminId): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            return;
        }

        $this->update([
            'status' => self::STATUS_UNDER_REVIEW,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);
    }

    /**
     * Records the manual bank transfer BRIX's Admin has confirmed they
     * already sent outside the application, and only then marks the payout
     * PAID. Only valid from APPROVED — the Admin must approve the request
     * before any transfer can be recorded, and a payout already PAID can
     * never be marked paid again (see PAYOUT IMMUTABILITY). The caller
     * (Admin\PayoutController::markPaid) is responsible for validating the
     * amount/currency against $this before calling this method.
     */
    public function markPaid(array $payment): void
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'transfer_reference' => $payment['transfer_reference'],
            'transfer_date' => $payment['transfer_date'],
            'paid_amount' => $payment['paid_amount'],
            'paid_currency' => $payment['paid_currency'],
            'payment_notes' => $payment['payment_notes'] ?? null,
            'paid_by' => $payment['paid_by'] ?? null,
            'provider_payout_id' => $payment['transfer_reference'],
        ]);

        // The commissions this payout claimed are now truly spent.
        $this->commissions()->update(['commission_status' => Commission::STATUS_PAID]);
        $this->settleReferralCommissions();

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
            'message' => "Payment request {$this->payout_code} has been paid.",
        ]);
    }

    public function markRejected(string $reason, ?int $rejectedBy = null): void
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED], true)) {
            return;
        }

        $this->releaseClaimedCommissions();

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'rejected_by' => $rejectedBy,
        ]);

        $this->organisation?->notifications()->create([
            'type' => 'payout_rejected',
            'title' => 'Payout rejected',
            'message' => "Payment request {$this->payout_code} was rejected: {$reason}",
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

        // Back to pending: the holding period is already behind them, so they
        // read as eligible again and can be claimed by a future payout.
        ReferralCommission::whereIn('id', $this->referralCommissions()->pluck('referral_commissions.id'))
            ->where('status', ReferralCommission::STATUS_IN_PAYOUT)
            ->update(['status' => ReferralCommission::STATUS_PENDING]);
    }

    /**
     * Marks the referral commissions this payout claimed as paid and credits
     * each to agency_ledger. The ledger's PAYOUT row debits the whole payout,
     * but nothing credits a referral commission there when it accrues (the
     * accrual only writes referral_commissions), so without these COMMISSION
     * rows the partner's ledger balance would go negative by the referral
     * portion. Runs once — markPaid() returns early for an already-paid payout.
     */
    private function settleReferralCommissions(): void
    {
        $claimed = $this->referralCommissions()
            ->where('referral_commissions.status', ReferralCommission::STATUS_IN_PAYOUT)
            ->get();

        foreach ($claimed as $commission) {
            AgencyLedger::create([
                'agency_id' => $this->agency_id,
                'store_id' => $commission->store_id,
                'payout_id' => $this->id,
                'type' => AgencyLedger::TYPE_COMMISSION,
                'amount' => $commission->pivot->amount,
                'description' => "Referral commission REF-{$commission->id} paid via {$this->payout_code}",
                'created_at' => now(),
            ]);
        }

        ReferralCommission::whereIn('id', $claimed->pluck('id'))->update(['status' => ReferralCommission::STATUS_PAID]);
    }
}

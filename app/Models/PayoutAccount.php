<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The real, canonical `payout_accounts` table — where BRIX sends a
 * partner's payouts. New writes from this app always go through the
 * encrypted `bank_account_number_encrypted` column, never the legacy
 * plain-text `bank_account_number` column some existing rows still use.
 */
class PayoutAccount extends Model
{
    /**
     * Public-facing method identifiers, used by forms/views/validation —
     * distinct from the real `type` column's enum('bank','upi'). Kept as
     * 'bank_transfer' (not 'bank') because that's what every existing
     * view/form already speaks; getTypeAttribute()/mutator below is the
     * only place the translation happens.
     */
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_UPI = 'upi';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'agency_id',
        'type',
        'account_holder_name',
        'bank_account_number_encrypted',
        'account_last4',
        'bank_ifsc',
        'account_type',
        'upi_vpa',
        'verification_status',
        'is_default',
    ];

    /**
     * The full bank account number is encrypted at rest and never
     * decrypted for display — only account_last4 is shown in the UI.
     */
    protected $casts = [
        'bank_account_number_encrypted' => 'encrypted',
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'bank_account_number',
        'bank_account_number_encrypted',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'agency_id', 'brix_agency_id');
    }

    /** Translates the public 'bank_transfer' identifier to the real enum's 'bank' on write. */
    public function setTypeAttribute(string $value): void
    {
        $this->attributes['type'] = $value === self::METHOD_BANK_TRANSFER ? 'bank' : $value;
    }

    /** Convenience alias — the real column stores 'bank', not 'bank_transfer'. */
    public function getMethodAttribute(): string
    {
        return $this->type === 'bank' ? self::METHOD_BANK_TRANSFER : $this->type;
    }

    /** Convenience alias — the real column is `bank_ifsc`. */
    public function getIfscCodeAttribute(): ?string
    {
        return $this->bank_ifsc;
    }

    /** Convenience alias — the real column is `upi_vpa`. */
    public function getUpiIdAttribute(): ?string
    {
        return $this->upi_vpa;
    }

    public function getMaskedAccountAttribute(): ?string
    {
        if ($this->type === self::METHOD_UPI) {
            return $this->upi_vpa;
        }

        return $this->account_last4 ? "•••• •••• {$this->account_last4}" : null;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->type === self::METHOD_UPI ? 'UPI' : 'Bank Transfer';
    }

    public function getAccountTypeLabelAttribute(): ?string
    {
        return match ($this->account_type) {
            'current' => 'Current',
            'savings' => 'Savings',
            default => null,
        };
    }
}

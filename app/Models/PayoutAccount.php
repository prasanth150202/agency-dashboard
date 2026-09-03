<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAccount extends Model
{
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_UPI = 'upi';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organisation_id',
        'method',
        'account_holder_name',
        'account_number',
        'account_last4',
        'ifsc_code',
        'upi_id',
        'verification_status',
    ];

    /**
     * The full bank account number is encrypted at rest and never
     * decrypted for display — only account_last4 is shown in the UI.
     */
    protected $casts = [
        'account_number' => 'encrypted',
    ];

    protected $hidden = [
        'account_number',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function getMaskedAccountAttribute(): ?string
    {
        if ($this->method === self::METHOD_UPI) {
            return $this->upi_id;
        }

        return $this->account_last4 ? "•••• •••• {$this->account_last4}" : null;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->method === self::METHOD_UPI ? 'UPI' : 'Bank Transfer';
    }
}

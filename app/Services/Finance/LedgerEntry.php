<?php

namespace App\Services\Finance;

use Illuminate\Support\Carbon;

/**
 * One row of the unified commission ledger, whichever ledger it came from.
 * A read-only view — it never carries a way to change the underlying row.
 */
final class LedgerEntry
{
    /** The legacy `transactions` ledger (store commissions). */
    public const SOURCE_STORE = 'store';

    /** The `referral_commissions` ledger. */
    public const SOURCE_REFERRAL = 'referral';

    public const SOURCES = [self::SOURCE_STORE, self::SOURCE_REFERRAL];

    public function __construct(
        public readonly string $source,
        public readonly int $id,
        public readonly string $reference,
        public readonly ?int $storeId,
        public readonly string $storeName,
        public readonly ?string $shopDomain,
        /** Order amount (store commissions) or verified revenue (referral commissions). */
        public readonly string $baseAmount,
        public readonly string $rate,
        public readonly string $amount,
        public readonly string $currency,
        /** Filterable status: pending|eligible|available|in_payout|paid|refunded|cancelled. */
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $badge,
        public readonly Carbon $date,
        public readonly ?Carbon $availableAt,
        public readonly ?Carbon $inPayoutAt,
        public readonly ?Carbon $paidAt,
        /** Referral link name for referral commissions. */
        public readonly ?string $via = null,
    ) {}

    public function sourceLabel(): string
    {
        return $this->source === self::SOURCE_REFERRAL ? 'Referral' : 'Store';
    }
}

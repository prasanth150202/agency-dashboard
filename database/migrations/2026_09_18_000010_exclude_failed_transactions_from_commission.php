<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * transactions.status = 'failed' means the underlying Shopify charge
     * never actually succeeded — no real revenue, so no real commission
     * either. The 2026_09_18_000001/000009 backfills didn't check this
     * column and left every row (failed included — 473 of them) eligible
     * to count as 'available' or even get swept into 'paid' by the
     * historical-payout reconciliation, overstating every affected
     * agency's balance. Re-flagging them 'cancelled' here excludes them
     * from AgencyFinanceService's sums (only STATUS_REFUNDED is excluded
     * from lifetimeEarnings by name, but 'cancelled' is never matched by
     * effectivelyAvailable()/stillPending(), so it's correctly invisible
     * everywhere balance is computed) without touching the real
     * transactions.status column those 473 rows already correctly carry.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->where('status', 'failed')
            ->update(['commission_status' => 'cancelled']);
    }

    public function down(): void
    {
        // Not meaningfully reversible — which rows this touched isn't
        // separately recorded. Re-deriving from transactions.status =
        // 'failed' (the same predicate up() used) is the recovery path.
    }
};

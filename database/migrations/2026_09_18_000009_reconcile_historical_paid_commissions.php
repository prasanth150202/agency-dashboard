<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 2026_09_18_000001 backfill marked every existing transaction
     * 'available', which is correct for transactions that were never
     * paid out — but real payouts.status='paid' rows (475 of them,
     * summing to real money already sent) predate this migration and
     * have no historical link to which transactions they covered (no
     * transaction_payout data exists for the past — that pivot only
     * starts tracking claims going forward). Left unreconciled,
     * AgencyFinanceService::availableBalance() would overstate every
     * agency's balance by exactly its historical paid total.
     *
     * Fix: for each agency, greedily mark its oldest transactions
     * 'paid' (FIFO, the same order claimCommissionsForPayout() uses
     * going forward) until their cumulative agency_commission reaches
     * that agency's real paid total. This is a best-effort reconstruction,
     * not a real historical record — there is no way to know which exact
     * transactions a given past payout claimed — but it makes the
     * balance this app shows match reality.
     */
    public function up(): void
    {
        $paidTotals = DB::table('payouts')
            ->where('status', 'paid')
            ->groupBy('agency_id')
            ->selectRaw('agency_id, SUM(amount) as paid_total')
            ->get();

        foreach ($paidTotals as $row) {
            $remaining = (float) $row->paid_total;

            if ($remaining <= 0) {
                continue;
            }

            $transactions = DB::table('transactions')
                ->where('agency_id', $row->agency_id)
                ->where('commission_status', 'available')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'agency_commission']);

            $toMarkPaid = [];

            foreach ($transactions as $transaction) {
                if ($remaining <= 0) {
                    break;
                }

                $toMarkPaid[] = $transaction->id;
                $remaining -= (float) $transaction->agency_commission;
            }

            if ($toMarkPaid !== []) {
                DB::table('transactions')->whereIn('id', $toMarkPaid)->update(['commission_status' => 'paid']);
            }
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way — which rows this touched
        // isn't itself recorded anywhere. Re-running the 000001 backfill
        // (available_at = created_at, status left at column default) on
        // affected rows would be the manual recovery path if ever needed.
    }
};

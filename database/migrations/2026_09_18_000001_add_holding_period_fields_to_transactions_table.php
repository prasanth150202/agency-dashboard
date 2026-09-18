<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restores the commission-holding-period state machine (previously
     * the local `commissions` table) directly onto the real, canonical
     * `transactions` table, so a partner payout and a future admin
     * approval act on the exact same row.
     *
     * `transactions.status` already exists and means something different
     * (success/pending/failed/refunded — did the underlying charge
     * succeed) — this is a separate column, `commission_status`, purely
     * about the agency-commission lifecycle. Existing rows are historical
     * demo data; backfilling them as already-'available' (available_at =
     * created_at) is the only sane default, since nothing here is
     * actually still pending.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('commission_status', 20)->default('available')->after('type')
                ->comment('pending, available, in_payout, paid, refunded, adjusted, cancelled');
            $table->timestamp('available_at')->nullable()->after('commission_status');
            $table->index(['agency_id', 'commission_status']);
        });

        DB::table('transactions')->update(['available_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'commission_status']);
            $table->dropColumn(['commission_status', 'available_at']);
        });
    }
};

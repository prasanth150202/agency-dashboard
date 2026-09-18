<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The real payouts.status enum has no 'cancelled' value (only
     * pending/approved/processing/paid/failed/rejected/reversed) — the
     * one status a partner can set on their own still-pending request,
     * distinct from BRIX rejecting it. MODIFY is required to extend a
     * MySQL enum; existing rows keep their current value either way.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payouts MODIFY status ENUM('pending','approved','processing','paid','failed','rejected','reversed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
            // Human-facing code (PAY-1234), distinct from idempotency_key
            // (an opaque value used only for the RAZORPAYX API call).
            $table->string('payout_code', 20)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'payout_code']);
        });

        DB::statement("ALTER TABLE payouts MODIFY status ENUM('pending','approved','processing','paid','failed','rejected','reversed') NOT NULL DEFAULT 'pending'");
    }
};

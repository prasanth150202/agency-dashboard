<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 'approved' and 'cancelled' join pending/processing/paid/rejected/
     * failed as valid Payout::STATUSES. 'approved' is set only by the
     * future BRIX admin review step (no admin UI ships in this change);
     * 'cancelled' is the one status an agency can set on their own
     * still-pending request, distinct from BRIX rejecting it.
     */
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'cancelled_at']);
        });
    }
};

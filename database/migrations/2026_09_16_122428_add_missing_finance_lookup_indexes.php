<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purely additive index changes for the Agency Commissions/Payouts
     * module — no columns, no data changes, no constraint that could
     * reject existing rows.
     *
     * - commissions.store_id: was only covered by the (organisation_id,
     *   status) composite; Store::commissions() (used by the "My Stores"
     *   card's withSum query) filters by store_id alone.
     * - payouts (organisation_id, status): AgencyFinanceService's
     *   pendingPayouts()/processingPayouts()/hasPendingOrProcessingPayout()/
     *   totalPaid() all filter on exactly this pair; the existing
     *   (organisation_id, date) composite doesn't serve them.
     *
     * Every other table already has the indexes/foreign keys/constraints
     * this module needs (verified via PRAGMA index_list/foreign_key_list
     * against the live schema, not just the migration history) — see the
     * findings written up alongside this migration.
     */
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->index('store_id');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'status']);
        });
    }
};

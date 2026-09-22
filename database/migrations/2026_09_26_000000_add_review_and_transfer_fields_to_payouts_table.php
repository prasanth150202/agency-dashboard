<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the fields the manual-payout review/approve/record-transfer flow
     * needs on top of the existing payouts lifecycle — who reviewed/approved/
     * rejected/paid it, and the manual bank transfer BRIX recorded (never an
     * actual gateway transaction). All additive; existing rows are unaffected.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payouts MODIFY status ENUM('pending','under_review','approved','processing','paid','failed','rejected','reversed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by')->nullable()->after('requested_at');
            $table->timestamp('reviewed_at')->nullable()->after('requested_by');
            $table->unsignedInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->unsignedInteger('approved_by')->nullable()->after('approved_at');
            $table->unsignedInteger('rejected_by')->nullable()->after('rejection_reason');

            $table->string('transfer_reference', 100)->nullable()->after('paid_at');
            $table->date('transfer_date')->nullable()->after('transfer_reference');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('transfer_date');
            $table->string('paid_currency', 3)->nullable()->after('paid_amount');
            $table->unsignedInteger('paid_by')->nullable()->after('paid_currency');
            $table->text('payment_notes')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'requested_by',
                'reviewed_at',
                'reviewed_by',
                'approved_by',
                'rejected_by',
                'transfer_reference',
                'transfer_date',
                'paid_amount',
                'paid_currency',
                'paid_by',
                'payment_notes',
            ]);
        });

        DB::statement("ALTER TABLE payouts MODIFY status ENUM('pending','approved','processing','paid','failed','rejected','reversed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};

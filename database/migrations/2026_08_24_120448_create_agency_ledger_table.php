<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail of every event that moves an agency's
     * balance: commissions realized, payouts paid, refunds, adjustments.
     * Financial records are never deleted — corrections are new rows.
     */
    public function up(): void
    {
        Schema::create('agency_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('transaction_id')->nullable(); // commissions.id or payouts.id, depending on type
            $table->string('type'); // COMMISSION, PAYOUT, REFUND, ADJUSTMENT
            $table->decimal('amount', 12, 2); // signed: +credit, -debit
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_ledger');
    }
};

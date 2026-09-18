<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a transaction (commission) to the payout that claimed it —
     * the real-schema equivalent of the old local `commission_payout`.
     * transactions.id and payouts.id are both `int unsigned`.
     */
    public function up(): void
    {
        Schema::create('transaction_payout', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('payout_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('payout_id')->references('id')->on('payouts')->cascadeOnDelete();
            $table->unique(['transaction_id', 'payout_id']);
            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payout');
    }
};

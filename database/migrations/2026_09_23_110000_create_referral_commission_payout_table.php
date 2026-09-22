<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which referral commissions a payout has claimed — the referral
        // counterpart of `transaction_payout`, which cannot be reused because
        // it holds a hard foreign key to the legacy `transactions` table.
        // A rejected or cancelled payout releases its commissions but keeps
        // its rows here, as history of the attempt.
        Schema::create('referral_commission_payout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_commission_id')->constrained('referral_commissions')->restrictOnDelete();
            // payouts.id is `int unsigned` in the real schema and no repo
            // migration owns that table, so no hard FK (same reasoning as
            // the other referral tables).
            $table->unsignedInteger('payout_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['referral_commission_id', 'payout_id'], 'uniq_referral_commission_payout');
            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commission_payout');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a commission to the payout that claimed it — the piece the
     * previous payout implementation didn't have: it reserved funds by
     * summing pending/processing payout amounts, never by locking
     * specific commission rows. This is what makes "which commissions
     * are inside payout #124" answerable, and what STATUS_IN_PAYOUT on
     * Commission actually means in practice. A commission may appear
     * here more than once over its lifetime (once per payout attempt)
     * if an earlier claim was released by a rejection/cancellation.
     */
    public function up(): void
    {
        Schema::create('commission_payout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['commission_id', 'payout_id']);
            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payout');
    }
};

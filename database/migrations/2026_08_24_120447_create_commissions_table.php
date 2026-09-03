<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per store payment to BRIX that generates agency commission.
     * The agency/BRIX split and the commission's source (agency default vs
     * a per-store custom rate) are snapshotted at creation time so later
     * rate changes never rewrite history.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->string('commission_source'); // agency_default, custom
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('brix_amount', 12, 2);
            $table->string('status')->default('pending'); // pending, available, paid, refunded, adjusted
            $table->date('transaction_date');
            $table->timestamp('available_at'); // when the holding period lifts
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('payout_code')->nullable()->unique()->after('id');
            $table->string('currency', 3)->default('INR')->after('amount');
            $table->string('payment_method')->nullable()->after('status'); // bank_transfer, upi, manual
            $table->foreignId('payout_account_id')->nullable()->after('payment_method')
                ->constrained()->nullOnDelete();
            $table->string('provider')->default('manual')->after('payout_account_id');
            $table->string('provider_payout_id')->nullable()->after('provider');
            $table->timestamp('requested_at')->nullable()->after('provider_payout_id');
            $table->timestamp('processing_at')->nullable()->after('requested_at');
            $table->timestamp('paid_at')->nullable()->after('processing_at');
            $table->timestamp('rejected_at')->nullable()->after('paid_at');
            $table->string('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
            $table->dropColumn([
                'payout_code', 'currency', 'payment_method', 'provider',
                'provider_payout_id', 'requested_at', 'processing_at',
                'paid_at', 'rejected_at', 'rejection_reason',
            ]);
        });
    }
};

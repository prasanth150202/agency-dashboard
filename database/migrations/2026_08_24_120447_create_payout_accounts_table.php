<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where BRIX sends an agency's payouts. Full bank account numbers are
     * stored encrypted (see PayoutAccount::$casts) and never re-displayed —
     * only account_last4 is shown in the UI.
     */
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // bank_transfer, upi
            $table->string('account_holder_name')->nullable();
            $table->text('account_number')->nullable(); // encrypted at rest
            $table->string('account_last4', 4)->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('verification_status')->default('not_configured'); // not_configured, pending_verification, verified, rejected
            $table->timestamps();

            $table->unique('organisation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};

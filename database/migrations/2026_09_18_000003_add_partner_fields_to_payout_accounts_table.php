<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The real payout_accounts.bank_account_number is plain text — fine
     * for existing legacy rows, but this app must never write sensitive
     * account numbers unencrypted. New writes from this app go through
     * `bank_account_number_encrypted` instead (Eloquent 'encrypted' cast)
     * and `account_last4` (masked display, never re-decrypted). The old
     * plain column is left as-is for whatever already populated it.
     */
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->text('bank_account_number_encrypted')->nullable()->after('bank_account_number');
            $table->string('account_last4', 4)->nullable()->after('bank_account_number_encrypted');
            $table->string('account_type', 20)->nullable()->after('bank_ifsc')->comment('savings, current');
        });
    }

    public function down(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_account_number_encrypted', 'account_last4', 'account_type']);
        });
    }
};

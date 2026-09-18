<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Balance/earnings/commission-rate figures moved out of here — the
        // real brix_superadmin schema already carries those (agencies
        // .commission_rate for the org-wide default, agency_ledger for the
        // running balance, transactions for lifetime earnings). This table
        // is left holding only the one thing that has no home there yet.
        Schema::create('organisation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_settings');
    }
};

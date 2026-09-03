<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_connection_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('state_token', 64)->unique();
            $table->string('shop_domain');
            // STARTED -> AUTHORIZING|INSTALL_REQUIRED -> INSTALLING -> COMPLETED,
            // or FAILED/EXPIRED at any point.
            $table->string('status')->default('STARTED');
            $table->string('failure_reason')->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['shop_domain', 'status']);
            $table->index(['organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_connection_attempts');
    }
};

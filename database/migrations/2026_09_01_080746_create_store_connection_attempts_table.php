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
            // stores.id is `int unsigned` in the real (merged) schema, not
            // Laravel's default bigint — foreignId()->constrained() would
            // create a mismatched column type and fail the FK constraint.
            $table->unsignedInteger('store_id')->nullable();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
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

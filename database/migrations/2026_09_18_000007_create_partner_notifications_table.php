<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partner-facing alerts (store connected, payout paid, etc.), scoped
     * to this app's own `organisations` login/tenant table. Distinct from
     * the real `notifications` table, which is admin_user_id-scoped —
     * the admin dashboard's own bell icon, a different audience entirely.
     */
    public function up(): void
    {
        Schema::create('partner_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('store_id')->nullable();
            $table->string('type')->default('info');
            $table->string('title');
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index(['organisation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_notifications');
    }
};

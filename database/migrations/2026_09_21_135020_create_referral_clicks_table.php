<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('agency_id');
            $table->foreignId('tracking_link_id')->constrained('tracking_links')->cascadeOnDelete();
            $table->string('referral_code', 40);
            $table->string('session_id', 64);
            $table->string('shop_domain')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agency_id', 'created_at']);
            $table->index(['tracking_link_id', 'created_at']);
            $table->index('shop_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};

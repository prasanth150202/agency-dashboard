<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('module'); // cart_drawer, fbt, coupon, upsell, progress_bar, sticky_add_to_cart, wishlist, trust_badges
            $table->string('status')->default('inactive'); // active, inactive
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_modules');
    }
};

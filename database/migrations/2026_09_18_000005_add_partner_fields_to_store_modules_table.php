<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real store_modules only has (store_id, module_name, is_active) —
     * module_name already holds the six real, live module labels ('Cart
     * Drawer', 'FBT', 'Coupons', 'Upsells', 'Progress Bar', 'AI BRIX').
     * Two modules the old local-only schema listed (Wishlist, Trust
     * Badges, Sticky Add to Cart) never appear in the real data and are
     * dropped as not actually live features.
     *
     * module_key is a stable slug for routes/toggles to key off, backfilled
     * from the existing module_name values.
     */
    public function up(): void
    {
        Schema::table('store_modules', function (Blueprint $table) {
            $table->string('module_key', 40)->nullable()->after('module_name');
            $table->timestamp('last_updated_at')->nullable()->after('is_active');
        });

        $map = [
            'Cart Drawer' => 'cart_drawer',
            'FBT' => 'fbt',
            'Coupons' => 'coupon',
            'Upsells' => 'upsell',
            'Progress Bar' => 'progress_bar',
            'AI BRIX' => 'ai_brix',
        ];

        foreach ($map as $name => $key) {
            DB::table('store_modules')->where('module_name', $name)->update(['module_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('store_modules', function (Blueprint $table) {
            $table->dropColumn(['module_key', 'last_updated_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('commission_source', 20)->default('agency_default')->after('commission_rate')
                ->comment('agency_default, custom — which rate was used at the time');
        });

        // app_settings is the real, admin-controlled key-value store
        // (previously the local-only `platform_settings` table). Seed the
        // two keys this app has always read, only if not already present.
        DB::table('app_settings')->insertOrIgnore([
            ['setting_key' => 'minimum_payout_amount', 'value' => '1000', 'updated_at' => now()],
            ['setting_key' => 'commission_holding_period_days', 'value' => '7', 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('commission_source');
        });

        DB::table('app_settings')->whereIn('setting_key', ['minimum_payout_amount', 'commission_holding_period_days'])->delete();
    }
};

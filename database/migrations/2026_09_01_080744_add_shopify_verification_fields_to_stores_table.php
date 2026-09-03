<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('installation_status')->default('NOT_INSTALLED')->after('status');
            $table->string('authorization_status')->default('NOT_AUTHORIZED')->after('installation_status');
            // Genuinely unknown right after a fresh install until looked up via the
            // Shopify Admin API — never fabricated, so this stays nullable.
            $table->string('shopify_shop_id')->nullable()->after('shop_domain');
            $table->timestamp('uninstalled_at')->nullable()->after('last_active_at');
        });

        // Existing seeded/demo stores already imply a real state via `status` —
        // map it forward rather than leaving every historical row at the
        // column defaults of NOT_INSTALLED/NOT_AUTHORIZED.
        DB::table('stores')
            ->whereIn('status', ['active', 'attention'])
            ->update(['installation_status' => 'INSTALLED', 'authorization_status' => 'AUTHORIZED']);

        DB::table('stores')
            ->where('status', 'offline')
            ->update([
                'installation_status' => 'UNINSTALLED',
                'authorization_status' => 'REVOKED',
                'uninstalled_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['installation_status', 'authorization_status', 'shopify_shop_id', 'uninstalled_at']);
        });
    }
};
